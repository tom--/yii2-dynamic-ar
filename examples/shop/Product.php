<?php

namespace examples\shop;

class Product extends \spinitron\dynamicAr\DynamicActiveRecord
{
    public function rules()
    {
        return [['dimensions.length', 'double', 'min' => 0.0]];
    }

    public function search($params)
    {
        $dataProvider = new \yii\data\ActiveDataProvider([
            'sort' => [
                'attributes' => [
                    'dimensions.length' => [
                        'asc' => ['(! dimensions.length !)' => SORT_DESC],
                        'desc' => ['(! dimensions.length !)' => SORT_ASC],
                    ],
                ],
            ],
            // ...
        ]);
    }

    public static function tableName()
    {
        return 'product';
    }

    public static function dynamicColumn()
    {
        return 'details';
    }
}
