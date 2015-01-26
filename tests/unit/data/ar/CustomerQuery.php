<?php

namespace tests\unit\data\ar;

use \spinitron\dynamicAr\DynamicActiveQuery;

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
