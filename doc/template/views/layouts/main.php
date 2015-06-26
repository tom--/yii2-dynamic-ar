<?php

use yii\bootstrap\Nav;
use yii\bootstrap\NavBar;
use yii\helpers\Html;
use yii\helpers\StringHelper;

/* @var $this yii\web\View */

\yii\apidoc\templates\bootstrap\assets\AssetBundle::register($this);

// Navbar hides initial content when jumping to in-page anchor
// https://github.com/twbs/bootstrap/issues/1768
$this->registerJs(<<<JS
    var shiftWindow = function () { scrollBy(0, -50) };
    if (location.hash) shiftWindow();
    window.addEventListener("hashchange", shiftWindow);
JS
,
    \yii\web\View::POS_READY
);

$this->context->pageTitle = "Dynamic Active Record";

$this->beginPage();
?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="language" content="en" />
    <?= Html::csrfMetaTags() ?>
    <?php $this->head() ?>
    <title><?php if (isset($type)) {
            echo Html::encode(StringHelper::basename($type->name) . " - {$this->context->pageTitle}");
        } elseif (isset($guideHeadline)) {
            echo Html::encode("$guideHeadline - {$this->context->pageTitle}");
        } else {
            echo Html::encode($this->context->pageTitle);
        }
    ?></title>
</head>
<body>

<?php $this->beginBody() ?>
<div class="wrap">
    <?php
    NavBar::begin([
        'brandLabel' => $this->context->pageTitle,
        'brandUrl' => ($this->context->apiUrl === null && $this->context->guideUrl !== null) ? './guide-index.html' : './index.html',
        'options' => [
            'class' => 'navbar-inverse navbar-fixed-top',
        ],
        'renderInnerContainer' => false,
        'view' => $this,
    ]);
    $nav = [];

    echo Nav::widget([
        'options' => ['class' => 'navbar-nav'],
        'items' => $nav,
        'view' => $this,
        'params' => [],
    ]);

    NavBar::end();
    ?>

    <?= $content ?>

</div>

<footer class="footer">
    <?php /* <p class="pull-left">&copy; My Company <?= date('Y') ?></p> */ ?>
    <p class="pull-right"><small>Page generated on <?= date('r') ?></small></p>
    <?= Yii::powered() ?>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
