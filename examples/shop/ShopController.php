<?php

namespace examples\shop;

use tests\unit\data\dar\Product;
use yii\data\ActiveDataProvider;

class ShopController extends \yii\base\Controller
{
    public function actionSomething()
    {
        $product = new Product([
            'sku' => 5463,
            'upc' => '234569',
            'price' => 4.99,
            'title' => 'Clue by four',
            'description' => 'Used for larting lusers or constructing things',
            'dimensions' => [
                'unit' => 'inch',
                'width' => 4,
                'height' => 2,
                'length' => 20,
            ],
            'material' => 'wood',
        ]);
        $product->save();

        /** @var Product $model */
        $model = new Product([
            'title' => 'Car',
            'specs.fuel.tank.capacity' => 50,
            'specs.fuel.tank.capacity.unit' => 'liter',
        ]);
        $model->setAttribute('specs.wheels.count', 4);
        $model = Product::find()->where(['(!dimensions.length!)' => 10]);
        $section = Product::find()
            ->select('CONCAT((! dimensions.width !), " x ", (! dimensions.height !))')
            ->where(['id' => 11])
            ->one();

    $product = new Product();
    $product->specs = new TvSpecs(['scenario' => 'insert']);
    $product->specs->load(Yii::$app->request->post());
    if (!$model->validate()) {
        // process validation errors
    }

    $product->save(false);
    $thatTv = Product::findOne($product->id);
    \yii\helpers\VarDumper::dump($thatTv->specs);
    // $thatTv->specs equals $product->specs->toArray()
    }
}
