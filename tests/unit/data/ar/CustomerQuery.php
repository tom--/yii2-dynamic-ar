<?php

namespace tests\unit\data\ar;

/**
 * CustomerQuery
 */
class CustomerQuery extends \app\db\DynamicActiveQuery
{
    public function active()
    {
        $this->andWhere('status=1');

        return $this;
    }
}
