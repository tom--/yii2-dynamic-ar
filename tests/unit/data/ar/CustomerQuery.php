<?php

namespace tests\unit\data\ar;

use app\db\DynamicActiveQuery;

/**
 * CustomerQuery
 */
class CustomerQuery extends DynamicActiveQuery
{
    public function active()
    {
        $this->andWhere('status=1');

        return $this;
    }
}
