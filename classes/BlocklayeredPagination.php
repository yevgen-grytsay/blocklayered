<?php
require_once _PS_MODULE_DIR_.'blocklayered/classes/BlocklayeredProductQuery.php';

/**
 * @author: yevgen
 * @date: 14.12.16
 */
class BlocklayeredPagination {
    private $price_ids = array();
    private $no_price_ids = array();
    private $on_page;
    /**
     * @var BlocklayeredProductQuery
     */
    private $qb;

    /**
     * Pagination constructor.
     * @param BlocklayeredProductQuery $qb
     * @param $on_page
     * @param array $all_ids
     * @param array $no_price_ids
     */
    public function __construct(BlocklayeredProductQuery $qb, $on_page, array $all_ids, array $no_price_ids = array())
    {
        $this->price_ids = array_diff($all_ids, $no_price_ids);
        $this->no_price_ids = array_intersect($all_ids, $no_price_ids); //TODO sort
        $this->on_page = $on_page;
        $this->qb = $qb;
    }

    public function getProducts($page) {
        $res = array_merge($this->price_ids, $this->no_price_ids);
        $page_ids = array_slice($res, ($page - 1) * $this->on_page, $this->on_page);
        $no_price = array_intersect($page_ids, $this->no_price_ids);
        if ($no_price) {
            return $this->qb->selectTail($page, $this->no_price_ids, $no_price);
        } else {
            return $this->qb->select($page, $this->no_price_ids);
        }
    }
}