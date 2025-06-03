<?php

namespace Automater\WC;

use AutomaterSDK\Exception\ApiException;
use AutomaterSDK\Exception\NotFoundException;
use AutomaterSDK\Exception\TooManyRequestsException;
use AutomaterSDK\Exception\UnauthorizedException;
use AutomaterSDK\Response\PaymentResponse;
use AutomaterSDK\Response\TransactionResponse;
use Exception;
use Automater\WC\Proxy;
use Automattic\WooCommerce\Utilities\NumberUtil;
use WC_Order;
use WC_Order_Item_Product;

class OrderProcessor {
	protected $integration;
	protected $proxy;

	public function __construct( Integration $integration ) {
		$this->integration = $integration;
		$this->proxy       = new Proxy( $integration->get_api_key(), $integration->get_api_secret() );
	}

	public function order_placed( $order_id ) {
		if ( ! $this->integration->api_enabled() ) {
			return;
		}

		if ( $this->integration->get_debug_log() ) {
			wc_get_logger()->notice( "Automater: Order has been placed: ID $order_id" );
		}

		$this->create_transaction( $order_id );
	}

	public function order_processing( $order_id ) {
		if ( ! $this->integration->api_enabled() ) {
			return;
		}

		if ( $this->integration->get_debug_log() ) {
			wc_get_logger()->notice( "Automater: Payment has been received: ID $order_id" );
		}

		$this->pay_transaction( $order_id );
	}

    protected function create_transaction( $order_id ) {
        $order = wc_get_order( $order_id );

        if (!empty($order->get_meta('automater_cart_id'))) {
            return;
        }

        $items = $order->get_items();
        if (empty($items)) {
            return;
        }

        $result   = [];
        $result[] = __( 'Automater codes:', 'automater' );

        $products = $this->transform_order_items( $items, $result );
        $this->create_automater_transaction( $products, $order, $result );
        $this->add_order_note( $result, $order );
        if ( $this->integration->get_debug_log() ) {
            wc_get_logger()->notice( 'Automater: ' . implode( ' | ', $result ) );
        }
    }

	protected function transform_order_items( array $items, array &$result ) {
		$products = [];
		/** @var WC_Order_Item_Product $item */
		foreach ( $items as $item ) {
			try {
				$automater_product_id = $this->integration->get_automater_product_id_for_wc_product( $item->get_product() );
				if ( ! $automater_product_id ) {
					$result[] = sprintf( __( 'Product not managed by automater: %s [%s]', 'automater' ), $item->get_name(), $item->get_id() );
					continue;
				}
				$qty = (int) $item->get_quantity();
				if ( $qty <= 0 || is_nan( $qty ) ) {
					$result[] = sprintf( __( 'Invalid quantity of product: %s [%s]', 'automater' ), $item->get_name(), $item->get_id() );
					continue;
				}
				$price = NumberUtil::round(((float)$item->get_total() + (float)$item->get_total_tax()) / $qty, 2);
				if ($price <= 0) {
					$result[] = sprintf( __( 'Invalid price of product: %s [%s]', 'automater' ), $item->get_name(), $item->get_id() );
					continue;
				}
				if ( ! isset( $products[ $automater_product_id ] ) ) {
					$products[ $automater_product_id ]['qty']      = 0;
					$products[ $automater_product_id ]['price']    = 0.0;
					$products[ $automater_product_id ]['currency'] = get_woocommerce_currency();
				}
				$products[ $automater_product_id ]['qty'] += $qty;
				$products[ $automater_product_id ]['price'] += $price;
			} catch ( Exception $e ) {
				$result[] = $e->getMessage() . sprintf( ': %s [%s]', $item->get_name(), $item->get_id() );
			}
		}

		return $products;
	}

	protected function create_automater_transaction( array $products, WC_Order $order, array &$result ) {
		if ( count( $products ) ) {
			if ( $this->integration->get_debug_log() ) {
				wc_get_logger()->notice( 'Automater: Creating automater transaction' );
				wc_get_logger()->notice( 'Automater: ' . $order->get_billing_email() );
				wc_get_logger()->notice( 'Automater: ' . $order->get_billing_phone() );
				wc_get_logger()->notice( 'Automater: ' . sprintf( __( 'Order from %s, id: #%s', 'automater' ), get_bloginfo( 'name' ), $order->get_order_number() ) );
			}

			$email = $order->get_billing_email();
			$phone = $order->get_billing_phone();
			$label = sprintf( __( 'Order from %s, id: #%s', 'automater' ), get_bloginfo( 'name' ), $order->get_order_number() );

			try {
				/** @var TransactionResponse $response */
				$response = $this->proxy->create_transaction( $products, $email, $phone, $label );
				if ( $this->integration->get_debug_log() ) {
					wc_get_logger()->notice( 'Automater: ' . var_export( $response, true ) );
				}
				if ( $response && $automater_cart_id = $response->getCartId() ) {
					$order->update_meta_data( 'automater_cart_id', $automater_cart_id );
					$order->save();
					$result[] = sprintf( __( 'Created cart number: %s', 'automater' ), $automater_cart_id );
				}
			} catch ( UnauthorizedException $exception ) {
				$this->handle_exception( $result, 'Invalid API key' );
			} catch ( TooManyRequestsException $e ) {
				$this->handle_exception( $result, 'Too many requests to Automater: ' . $e->getMessage() );
			} catch ( NotFoundException $e ) {
				$this->handle_exception( $result, 'Not found - invalid params' );
			} catch ( ApiException $e ) {
				$this->handle_exception( $result, $e->getMessage() );
			}
		}
	}

	protected function add_order_note( array $status, WC_Order $order ) {
		if ( ! $order ) {
			return;
		}
		$order->add_order_note( implode( '<br>', $status ) );
	}

	protected function pay_transaction( $order_id ) {
		$order             = wc_get_order( $order_id );

        $orderData = $order->get_data();
        if (isset($orderData['payment_method']) && $orderData['payment_method'] == 'cod') {
            return;
        }

		$automater_cart_id = $order->get_meta( 'automater_cart_id' );

		if ( ! $automater_cart_id ) {
			return;
		}

		$result   = [];
		$result[] = __( 'Automater codes:', 'automater' );

		$this->create_automater_payment( $order, $automater_cart_id, $result );
		$this->add_order_note( $result, $order );
		if ( $this->integration->get_debug_log() ) {
			wc_get_logger()->notice( 'Automater: ' . implode( ' | ', $result ) );
		}
	}

	protected function create_automater_payment( WC_Order $order, $automater_cart_id, &$result ) {
		$payment_id  = $order->get_id();
		$amount      = $order->get_total();
		$description = $order->get_payment_method();
		$currency    = $order->get_currency();

		try {
			/** @var PaymentResponse $response */
			$response = $this->proxy->create_payment( $automater_cart_id, $payment_id, $amount, $currency, $description );
			if ( $this->integration->get_debug_log() ) {
				wc_get_logger()->notice( 'Automater: ' . var_export( $response, true ) );
			}
			if ( $response ) {
				$result[] = sprintf( __( 'Automater - paid successfully: %s', 'automater' ), $automater_cart_id );
			}
		} catch ( UnauthorizedException $exception ) {
			$this->handle_exception( $result, 'Invalid API key' );
		} catch ( TooManyRequestsException $e ) {
			$this->handle_exception( $result, 'Too many requests to Automater: ' . $e->getMessage() );
		} catch ( NotFoundException $e ) {
			$this->handle_exception( $result, 'Not found - invalid params' );
		} catch ( ApiException $e ) {
			$this->handle_exception( $result, $e->getMessage() );
		}
	}

	protected function handle_exception( array &$result, $exception_message ) {
		if ( $this->integration->get_debug_log() ) {
			wc_get_logger()->notice( 'Automater: ' . $exception_message );
		}
		$result[] = 'Automater: ' . $exception_message;
	}
}
