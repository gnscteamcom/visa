<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Transator */

$this->title = '编辑: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => '办理人', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->tid]];
$this->params['breadcrumbs'][] = '更新';
?>
<div class="transator-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
