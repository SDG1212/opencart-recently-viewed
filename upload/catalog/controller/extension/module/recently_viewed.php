<?php
/**
 * @author Dmytro Sokolenko <sokol1294@gmail.com>
 * @license GNU General Public License, version 3. See LICENSE
 */
class ControllerExtensionModuleRecentlyViewed extends Controller {
	public function index($setting) {
		if (!$setting['limit']) {
			$setting['limit'] = 4;
		}

		$recently_viewed_products = array();

		if (!empty($this->request->cookie['recently_viewed'])) {
      $recently_viewed_products = json_decode(base64_decode($this->request->cookie['recently_viewed']), true);

      unset($recently_viewed_products[$this->request->get['product_id']]);

      if (sizeof($recently_viewed_products) > $setting['limit']) {
        unset($recently_viewed_products[array_search(min($recently_viewed_products), $recently_viewed_products)]);
      }
		}

    $products = $recently_viewed_products;

    $recently_viewed_products[$this->request->get['product_id']] = time();

		$recently_viewed_products = base64_encode(json_encode($recently_viewed_products));

		setcookie('recently_viewed', $recently_viewed_products, 0, '/', $this->request->server['HTTP_HOST']);

		$this->load->language('extension/module/recently_viewed');

		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		$data['products'] = array();

		if (empty($products)) {
			return;
		}

		arsort($products);

		foreach ($products as $product_id => $time) {
			$product_info = $this->model_catalog_product->getProduct($product_id);

			if ($product_info) {
				if ($product_info['image']) {
					$image = $this->model_tool_image->resize($product_info['image'], $setting['width'], $setting['height']);
				} else {
					$image = $this->model_tool_image->resize('placeholder.png', $setting['width'], $setting['height']);
				}

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$price = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$price = false;
				}

				if (!is_null($product_info['special']) && (float)$product_info['special'] >= 0) {
					$special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
					$tax_price = (float)$product_info['special'];
				} else {
					$special = false;
					$tax_price = (float)$product_info['price'];
				}
	
				if ($this->config->get('config_tax')) {
					$tax = $this->currency->format($tax_price, $this->session->data['currency']);
				} else {
					$tax = false;
				}

				if ($this->config->get('config_review_status')) {
					$rating = $product_info['rating'];
				} else {
					$rating = false;
				}

				$data['products'][] = array(
					'product_id'  => $product_info['product_id'],
					'thumb'       => $image,
					'name'        => $product_info['name'],
					'description' => utf8_substr(strip_tags(html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8')), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
					'price'       => $price,
					'special'     => $special,
					'tax'         => $tax,
					'rating'      => $rating,
					'href'        => $this->url->link('product/product', 'product_id=' . $product_info['product_id'])
				);
			}
		}

		if ($data['products']) {
			return $this->load->view('extension/module/recently_viewed', $data);
		}
	}
}
