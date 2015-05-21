<?php
/**
 * @author Carsten Brandt <mail@cebe.cc>
 */

namespace tests\unit\data\ar;

use tests\unit\data\BaseRecord;

/**
 * Class Profile
 *
 * @property integer $id
 * @property string $description
 *
 */
class Profile extends BaseRecord
{


    public static function tableName()
    {
        return 'profile';
    }
}
