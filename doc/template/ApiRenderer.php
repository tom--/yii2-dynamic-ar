<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace spinitron\dynamicAr\doc\template;

use Yii;

/**
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class ApiRenderer extends \yii\apidoc\templates\bootstrap\ApiRenderer
{
    public $indexView = '@yii/apidoc/templates/bootstrap/views/index.php';

    public $foobar = 'baz';

    /**
     * @inheritdoc
     */
    public function render($context, $targetDir)
    {
        $this->layout = __DIR__ . '/views/layouts/api.php';

        $keep = [
            'spinitron\\dynamicAr\\DynamicActiveQuery',
            'spinitron\\dynamicAr\\DynamicActiveRecord',
            'yii\\db\\ActiveQuery',
            'yii\\db\\Query',
            'yii\\base\\Component',
            'yii\\base\\Object',
            'yii\\base\\Configurable',
            'yii\\base\\Arrayable',
            'yii\\base\\ArrayableTrait',
            'yii\\db\\ActiveRecordInterface',
            'yii\\db\\ActiveRecord',
            'yii\\db\\BaseActiveRecord',
            'yii\\base\\Model',
            'yii\\db\\ActiveQueryInterface',
            'yii\\db\\QueryInterface',
            'yii\\base\\ArrayableTrait',
            'yii\\db\\ActiveQueryTrait',
            'yii\\db\\ActiveRelationTrait',
            'yii\\db\\QueryTrait',
        ];

        $types = [];
        foreach (['classes', 'interfaces', 'traits'] as $kind) {
            $contextKind = & $context->$kind;
            foreach ($contextKind as $className => $type) {
                if (!in_array($className, $keep)) {
                    unset($contextKind[$className]);
                } else {
                    $types[$className] = $type;
                }
            }
        }

        parent::render($context, $targetDir);

        $myDocs = [
            'doc/design.md' => 'doc-design.html',
            'doc/datatypes.md' => 'doc-datatypes.html',
            'README.md' => 'index.html',
        ];
        foreach ($myDocs as $in => $out) {
            $readme = @file_get_contents($in);
            $fileContent = $this->renderWithLayout($this->indexView, [
                'docContext' => $context,
                'types' => $types,
                'readme' => $readme ?: null,
            ]);
            file_put_contents($targetDir . '/' . $out, $fileContent);
        }

        unlink($targetDir . '/jssearch.index.js');
    }
}
