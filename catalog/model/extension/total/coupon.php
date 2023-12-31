<?php
class ModelExtensionTotalCoupon extends Model {
	
	public function getCouponList() {

		$coupon_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon` WHERE ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) AND status = '1'");
		$list = array();

		if ($coupon_query->num_rows) {
			foreach($coupon_query->rows as $result){
				$status = true;
				if ($result['total'] > $this->cart->getSubTotal()) {
					$status = false;
				}
				$coupon_history_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch WHERE ch.coupon_id = '" . (int)$result['coupon_id'] . "'");
				if ($result['uses_total'] > 0 && ($coupon_history_query->row['total'] >= $result['uses_total'])) {
					$status = false;
				}
				if ($result['logged'] && !$this->customer->getId()) {
					$status = false;
				}
				if ($this->customer->getId()) {
					$coupon_history_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch WHERE ch.coupon_id = '" . (int)$result['coupon_id'] . "' AND ch.customer_id = '" . (int)$this->customer->getId() . "'");
					if ($result['uses_customer'] > 0 && ($coupon_history_query->row['total'] >= $result['uses_customer'])) {
						$status = false;
					}
				}
				
				// Customer Group
				//debug($this->customer->getGroupId());
				$group_status = false;
				$customer_group_info = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_customer_group` WHERE coupon_id = '" . (int)$result['coupon_id'] . "'");
				if($customer_group_info->num_rows){
					foreach($customer_group_info->rows as $customer_group){
						if($customer_group['customer_group_id'] == $this->customer->getGroupId()){
							$group_status = true;
						}
					}
				}else{
					$group_status = true;
				}
				
				// Customer
				$customer_status = false;
				$customer_info = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_customer` WHERE coupon_id = '" . (int)$result['coupon_id'] . "'");
				if($customer_info->num_rows){
					foreach($customer_info->rows as $customer){
						if($customer['customer_id'] == $this->customer->getId()){
							$customer_status = true;
						}
					}
				}
				
				if(!$group_status && !$customer_status){
					$status = false;
				}

				// Products
				$coupon_product_data = array();

				$coupon_product_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_product` WHERE coupon_id = '" . (int)$result['coupon_id'] . "'");

				foreach ($coupon_product_query->rows as $product) {
					$coupon_product_data[] = $product['product_id'];
				}

				// Categories
				$coupon_category_data = array();

				$coupon_category_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_category` cc LEFT JOIN `" . DB_PREFIX . "category_path` cp ON (cc.category_id = cp.path_id) WHERE cc.coupon_id = '" . (int)$result['coupon_id'] . "'");

				foreach ($coupon_category_query->rows as $category) {
					$coupon_category_data[] = $category['category_id'];
				}

				// Brands
				$coupon_brand_data = array();

				$coupon_brand_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_manufacturer` WHERE coupon_id = '" . (int)$result['coupon_id'] . "'");

				foreach ($coupon_brand_query->rows as $brand) {
					$coupon_brand_data[] = $brand['manufacturer_id'];
				}

				$product_data = array();

				if ($coupon_product_data || $coupon_category_data || $coupon_brand_data) {
					foreach ($this->cart->getProducts() as $product) {
						if (in_array($product['product_id'], $coupon_product_data)) {
							$product_data[] = $product['product_id'];
							continue;
						}

						foreach ($coupon_category_data as $category_id) {
							$coupon_category_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product_to_category` WHERE `product_id` = '" . (int)$product['product_id'] . "' AND category_id = '" . (int)$category_id . "'");

							if ($coupon_category_query->row['total']) {
								$product_data[] = $product['product_id'];
								continue;
							}
						}

						if (in_array($product['manufacturer_id'], $coupon_brand_data)) {
							$product_data[] = $product['product_id'];
							continue;
						}
					}

					if (!$product_data) {
						$status = false;
					}
				}
			}
			if($status){
				$list[] = array(
					'coupon_id'     => $coupon_query->row['coupon_id'],
					'code'          => $coupon_query->row['code'],
					'name'          => $coupon_query->row['name'],
					'type'          => $coupon_query->row['type'],
					'discount'      => $coupon_query->row['discount'],
					'shipping'      => $coupon_query->row['shipping'],
					'total'         => $coupon_query->row['total'],
					'max_total'     => $coupon_query->row['max_total'],
					'product'       => $product_data,
					'date_start'    => $coupon_query->row['date_start'],
					'date_end'      => $coupon_query->row['date_end'],
					'uses_total'    => $coupon_query->row['uses_total'],
					'uses_customer' => $coupon_query->row['uses_customer'],
					'status'        => $coupon_query->row['status'],
					'date_added'    => $coupon_query->row['date_added'],
					'valid'        => $status,
				);
			}
		}
		return $list;
	}

	public function getCoupon($code,$order_id=0, $return_extra=false) {
		$status = true;
		$message = '';

		$coupon_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon` WHERE code = '" . $this->db->escape($code) . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) AND status = '1'");

		if ($coupon_query->num_rows) {
			if ($coupon_query->row['total'] > $this->cart->getSubTotal()) {
				$status = false;
				$message = 'Order amount must reach minimum '.$this->currency->format($coupon_query->row['total'], $this->session->data['currency']);
			}

            if($order_id > 0){
    			$coupon_history_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch WHERE ch.coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "' AND ch.order_id != '" . $order_id . "'");
            }else{
                $coupon_history_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch WHERE ch.coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");
            }
			
			if ($coupon_query->row['uses_total'] > 0 && ($coupon_history_query->row['total'] >= $coupon_query->row['uses_total'])) {
				$status = false;
				$message = 'Coupon Code reach its usage limit';
			}

			if ($coupon_query->row['logged'] && !$this->customer->getId()) {
				$status = false;
				$message = 'Please login to use this Coupon Code';
			}

			if ($this->customer->getId()) {
				if($order_id > 0){
					$coupon_history_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch WHERE ch.coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "' AND ch.customer_id = '" . (int)$this->customer->getId() . "' AND ch.order_id != '" . $order_id . "'");
				}else{
					$coupon_history_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch WHERE ch.coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "' AND ch.customer_id = '" . (int)$this->customer->getId() . "'");
				}
				if ($coupon_query->row['uses_customer'] > 0 && ($coupon_history_query->row['total'] >= $coupon_query->row['uses_customer'])) {
					$status = false;
					$message = 'Coupon Code reach its usage limit';
				}
			}
			
			// Customer Group
			$group_status = false;
			$customer_group_info = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_customer_group` WHERE coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");
			if($customer_group_info->num_rows){
				foreach($customer_group_info->rows as $customer_group){
					if($customer_group['customer_group_id'] == $this->customer->getGroupId()){
						$group_status = true;
					}
				}
			}else{
				$group_status = true;
			}

			// Customer
			$customer_status = false;
			$customer_info = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_customer` WHERE coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");
			if($customer_info->num_rows){
				foreach($customer_info->rows as $customer){
					if($customer['customer_id'] == $this->customer->getId()){
						$customer_status = true;
					}
				}
			}

			if(!$group_status && !$customer_status){
				$status = false;
				$message = 'This coupon code is not valid';
			}

			// Products
			$coupon_product_data = array();

			$coupon_product_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_product` WHERE coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");

			foreach ($coupon_product_query->rows as $product) {
				$coupon_product_data[] = $product['product_id'];
			}

			// Categories
			$coupon_category_data = array();

			$coupon_category_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_category` cc LEFT JOIN `" . DB_PREFIX . "category_path` cp ON (cc.category_id = cp.path_id) WHERE cc.coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");

			foreach ($coupon_category_query->rows as $category) {
				$coupon_category_data[] = $category['category_id'];
			}

			// Brands
			$coupon_brand_data = array();

			$coupon_brand_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_manufacturer` WHERE coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");

			foreach ($coupon_brand_query->rows as $brand) {
				$coupon_brand_data[] = $brand['manufacturer_id'];
			}

			$product_data = array();

			if ($coupon_product_data || $coupon_category_data || $coupon_brand_data) {
				foreach ($this->cart->getProducts() as $product) {
					if (in_array($product['product_id'], $coupon_product_data)) {
						$product_data[] = $product['product_id'];

						continue;
					}

					foreach ($coupon_category_data as $category_id) {
						$coupon_category_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product_to_category` WHERE `product_id` = '" . (int)$product['product_id'] . "' AND category_id = '" . (int)$category_id . "'");

						if ($coupon_category_query->row['total']) {
							$product_data[] = $product['product_id'];

							continue;
						}
					}

					if (in_array($product['manufacturer_id'], $coupon_brand_data)) {
						$product_data[] = $product['product_id'];

						continue;
					}
				}

				if (!$product_data) {
					$status = false;
					$message = 'This coupon code is only valid for certain product';
				}
			}
		} else {
			$status = false;
			$message = 'Please enter a valid Coupon Code';
		}

			if ($status) {
				return array(
					'coupon_id'     => $coupon_query->row['coupon_id'],
					'code'          => $coupon_query->row['code'],
					'name'          => $coupon_query->row['name'],
					'type'          => $coupon_query->row['type'],
					'discount'      => $coupon_query->row['discount'],
					'shipping'      => $coupon_query->row['shipping'],
					'total'         => $coupon_query->row['total'],
					'max_total'     => $coupon_query->row['max_total'],
					'product'       => $product_data,
					'date_start'    => $coupon_query->row['date_start'],
					'date_end'      => $coupon_query->row['date_end'],
					'uses_total'    => $coupon_query->row['uses_total'],
					'uses_customer' => $coupon_query->row['uses_customer'],
					'status'        => $coupon_query->row['status'],
					'date_added'    => $coupon_query->row['date_added'],
					'valid'        => $status,
					'message'      => $message
				);
			}else{
				if($return_extra){
					return array(
						'valid'        => $status,
						'message'      => $message
					);
				}
			}
		
	}

	public function getTotal($total,$order_id=0) {
		if (isset($this->session->data['coupon'])) {
			$this->load->language('extension/total/coupon');
	
			$coupon_info = $this->getCoupon($this->session->data['coupon'],$order_id);
			
			if ($coupon_info) {
				$discount_total = 0;

				if (!$coupon_info['product']) {
					$sub_total = $this->cart->getSubTotal();
				} else {
					$sub_total = 0;

					foreach ($this->cart->getProducts() as $product) {
						if (in_array($product['product_id'], $coupon_info['product'])) {
							$sub_total += $product['total'];
						}
					}
				}

				if ($coupon_info['type'] == 'F') {
					$coupon_info['discount'] = min($coupon_info['discount'], $sub_total);
				}

				foreach ($this->cart->getProducts() as $product) {
					$discount = 0;

					if (!$coupon_info['product']) {
						$status = true;
					} else {
						$status = in_array($product['product_id'], $coupon_info['product']);
					}

					if ($status) {
						if ($coupon_info['type'] == 'F') {
							$discount = $coupon_info['discount'] * ($product['total'] / $sub_total);
						} elseif ($coupon_info['type'] == 'P') {
							$discount = $product['total'] / 100 * $coupon_info['discount'];
						}

						if ($product['tax_class_id']) {
							$tax_rates = $this->tax->getRates($product['total'] - ($product['total'] - $discount), $product['tax_class_id']);

							foreach ($tax_rates as $tax_rate) {
								if ($tax_rate['type'] == 'P') {
									$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
								}
							}
						}
					}

					$discount_total += $discount;
				}

				if ($coupon_info['shipping'] && isset($this->session->data['shipping_method'])) {
					if (!empty($this->session->data['shipping_method']['tax_class_id'])) {
						$tax_rates = $this->tax->getRates($this->session->data['shipping_method']['cost'], $this->session->data['shipping_method']['tax_class_id']);

						foreach ($tax_rates as $tax_rate) {
							if ($tax_rate['type'] == 'P') {
								$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
							}
						}
					}

					$discount_total += $this->session->data['shipping_method']['cost'];
				}
				
				if($discount_total > $coupon_info['max_total'] && $coupon_info['max_total'] != '0.000'){
					$discount_total = $coupon_info['max_total'];
				}

				// If discount greater than total
				if ($discount_total > $total) {
					$discount_total = $total;
				}

				if ($discount_total > 0) {
					$total['totals'][] = array(
						'code'       => 'coupon',
						'title'      => sprintf($this->language->get('text_coupon'), $this->session->data['coupon']),
						'value'      => -$discount_total,
						'sort_order' => $this->config->get('coupon_sort_order')
					);

					//$total['total'] -= $discount_total;
					$total['total'] = round($total['total'], 2); // fixes for 115.9 - 115.9 = 1.4210854715202E-14 - Floating Point Precision Issue
					$total['total'] -= round($discount_total, 2);
				}
			}
		}
	}

	public function confirm($order_info, $order_total) {
		$code = '';

		$start = strpos($order_total['title'], '(') + 1;
		$end = strrpos($order_total['title'], ')');

		if ($start && $end) {
			$code = substr($order_total['title'], $start, $end - $start);
		}

		if ($code) {
			$coupon_info = $this->getCoupon($code);

			if ($coupon_info) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "coupon_history` SET coupon_id = '" . (int)$coupon_info['coupon_id'] . "', order_id = '" . (int)$order_info['order_id'] . "', customer_id = '" . (int)$order_info['customer_id'] . "', amount = '" . (float)$order_total['value'] . "', date_added = NOW()");
			} else {
				return $this->config->get('config_fraud_status_id');
			}
		}
	}

	public function unconfirm($order_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "coupon_history` WHERE order_id = '" . (int)$order_id . "'");
	}
}
