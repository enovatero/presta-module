<?php


class EnovateCustomers {

    public function findWmeCustomerCode(int $customerId) {

        $sql = "SELECT customer_code 
                FROM `"._DB_PREFIX_."enovate_customers` envc
                WHERE envc.status = 1 AND envc.id_customer = '" . pSQL($customerId) . "'
                ";
        $customerCode = Db::getInstance()->getValue($sql);

        return $customerCode ?: null;
    }
}