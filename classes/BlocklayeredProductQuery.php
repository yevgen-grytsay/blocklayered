<?php

/**
 * @author: yevgen
 * @date: 14.12.16
 */
class BlocklayeredProductQuery {
    private $on_page;
    private $alias_where;
    private $nb_day_new_product;
    private $context;
    private $cookie;

    /**
     * BlocklayeredProductQuery constructor.
     * @param $on_page
     * @param $alias_where
     * @param $nb_day_new_product
     * @param $context
     * @param $cookie
     */
    public function __construct($on_page, $alias_where, $nb_day_new_product, $context, $cookie)
    {
        $this->on_page = $on_page;
        $this->alias_where = $alias_where;
        $this->nb_day_new_product = $nb_day_new_product;
        $this->context = $context;
        $this->cookie = $cookie;
    }

    /**
     * @param $page
     * @param array $exclude_ids
     * @return array
     */
    public function select($page, $exclude_ids = array()) {
        $exclude = $this->getExcludeSql($exclude_ids);
        return $this->query($this->createSql($exclude, $this->getLimitSql($page)));
    }

    /**
     * @param $page
     * @param array $exclude_ids
     * @param array $no_price_ids
     * @return array
     */
    public function selectTail($page, $exclude_ids = array(), $no_price_ids = array()) {
        $head = $this->select($page, $exclude_ids);
        $tail = $this->selectProducts($no_price_ids);
        return array_merge($head, $tail);
    }

    private function selectProducts($ids) {
        return $this->query($this->createSql($this->getIncludeSql($ids), ''));
    }

    private function query($sql) {
        return Db::getInstance()->executeS($sql, true, false);
    }

    /**
     * @param $include_ids
     * @return string
     */
    private function getIncludeSql($include_ids)
    {
        $include = '';
        if ($include_ids) {
            $include = sprintf(' AND %s.id_product IN(%s)', $this->alias_where, implode(', ', $include_ids));
        }
        return $include;
    }

    /**
     * @param $exclude_ids
     * @return string
     */
    private function getExcludeSql($exclude_ids)
    {
        $exclude = '';
        if ($exclude_ids) {
            $exclude = sprintf(' AND %s.id_product NOT IN(%s)', $this->alias_where, implode(', ', $exclude_ids));
        }
        return $exclude;
    }

    private function getLimitSql($page) {
        $offset = ((int)$page - 1) * $this->on_page;
        return ' LIMIT '.($offset .','.$this->on_page);
    }

    /**
     * @param string $ids_where
     * @param string $limit
     * @return string
     */
    private function createSql($ids_where = '', $limit = '') {
        return '
				SELECT
					p.*,
					' . ($this->alias_where == 'p' ? '' : 'product_shop.*,') . '
					' . $this->alias_where . '.id_category_default,
					pl.*,
					MAX(image_shop.`id_image`) id_image,
					il.legend,
					m.name manufacturer_name,
					' . (Combination::isFeatureActive() ? 'MAX(product_attribute_shop.id_product_attribute) id_product_attribute,' : '') . '
					DATEDIFF(' . $this->alias_where . '.`date_add`, DATE_SUB("' . date('Y-m-d') . ' 00:00:00", INTERVAL ' . (int)$this->nb_day_new_product . ' DAY)) > 0 AS new,
					stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity' . (Combination::isFeatureActive() ? ', MAX(product_attribute_shop.minimal_quantity) AS product_attribute_minimal_quantity' : '') . '
				FROM ' . _DB_PREFIX_ . 'cat_filter_restriction cp
				LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = cp.`id_product`
				' . Shop::addSqlAssociation('product', 'p') .
            (Combination::isFeatureActive() ?
                'LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON (p.`id_product` = pa.`id_product`)
				' . Shop::addSqlAssociation('product_attribute', 'pa', false, 'product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int)$this->context->shop->id) : '') . '
				LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (pl.id_product = p.id_product' . Shop::addSqlRestrictionOnLang('pl') . ' AND pl.id_lang = ' . (int)$this->cookie->id_lang . ')
				LEFT JOIN `' . _DB_PREFIX_ . 'image` i  ON (i.`id_product` = p.`id_product`)' .
            Shop::addSqlAssociation('image', 'i', false, 'image_shop.cover=1') . '
				LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int)$this->cookie->id_lang . ')
				LEFT JOIN ' . _DB_PREFIX_ . 'manufacturer m ON (m.id_manufacturer = p.id_manufacturer)
				' . Product::sqlStock('p', 0) . '
				WHERE ' . $this->alias_where . '.`active` = 1 AND ' . $this->alias_where . '.`visibility` IN ("both", "catalog")' .
                    $ids_where.
            'GROUP BY product_shop.id_product
				ORDER BY ' . Tools::getProductsOrder('by', Tools::getValue('orderby'), true) . ' ' . Tools::getProductsOrder('way', Tools::getValue('orderway')) . ' , cp.id_product'.
            $limit;
    }
}