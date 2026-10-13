<?php
use yii\helpers\Html;
use yii\helpers\Url;
use webvimark\modules\UserManagement\UserManagementModule as UM;
$this->title = UM::t('back', 'Overview');
?>
<div class="row g-3 mb-4">
<?php $statRoutes = ['Users' => 'user', 'Roles' => 'role', 'Permissions' => 'permission', 'Visit log' => 'user-visit-log']; foreach ($stats as $label => $value): ?>
<div class="col-6 col-lg-3"><a class="card h-100 stat-link" href="<?= Url::to(['/user-management/' . $statRoutes[$label] . '/index']) ?>"><div class="card-body">
<div class="text-body-secondary"><?= Html::encode(UM::t('back', $label)) ?></div>
<div class="fs-2 fw-semibold"><?= (int)$value ?></div>
</div></a></div>
<?php endforeach ?>
</div>
<section class="card um-card" aria-labelledby="activity-title">
<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
<div><h2 id="activity-title" class="h5 mb-1"><?= Html::encode(UM::t('back', 'Access activity')) ?></h2><span class="text-body-secondary small"><?= Html::encode(UM::t('back', 'Recorded logins per day · Last 30 days')) ?></span></div>
<?= \webvimark\modules\UserManagement\components\GhostHtml::a(Html::encode(UM::t('back', 'Visit log')), ['/user-management/user-visit-log/index'], ['class'=>'btn btn-primary btn-sm']) ?>
</div>
<div class="card-body">
<?php if ($activity === null): ?>
<p class="text-body-secondary mb-0"><?= Html::encode(UM::t('back', 'You need access to the visit log to view this chart.')) ?></p>
<?php else: ?>
<div class="row g-3 mb-4">
<?php foreach (['Recorded logins'=>$activity['total'], 'Users with activity'=>$activity['users'], 'Highest daily count'=>$activity['peak']] as $label=>$value): ?>
<div class="col-12 col-sm-4"><div class="text-body-secondary small"><?= Html::encode(UM::t('back', $label)) ?></div><strong class="fs-4"><?= Yii::$app->formatter->asInteger($value) ?></strong></div>
<?php endforeach ?>
</div>
<?php if ($activity['total'] === 0): ?>
<p class="text-body-secondary"><?= Html::encode(UM::t('back', 'No logins have been recorded in this period.')) ?></p>
<?php endif ?>
<?php
$step = max(1, (int)ceil($activity['peak'] / 4));
$maximum = $step * 4;

?>
<div class="activity-figure" role="img" aria-label="<?= Html::encode(UM::t('back', 'Daily recorded logins')) ?>">
<div class="activity-plot">
<div class="activity-y-axis" aria-hidden="true"><?php for ($tick=4; $tick>=0; $tick--): ?><span><?= $tick*$step ?></span><?php endfor ?></div>
<div class="activity-bars">
<?php foreach ($activity['series'] as $index=>$day): $label=Yii::$app->formatter->asDate($day['date'],'d MMM'); ?>
<div class="activity-day" title="<?= Html::encode($label . ': ' . UM::t('back', '{count} recorded logins', ['count'=>$day['count']])) ?>"><span class="activity-bar-fill" style="height:<?= $day['count']/$maximum*100 ?>%"></span></div>
<?php endforeach ?>
</div></div>
<div class="activity-dates" aria-hidden="true">
<?php foreach ($activity['series'] as $index=>$day): if ($index % 5 === 0 || $index === 29): ?>
<span class="<?= in_array($index,[5,15,25],true) ? 'activity-date-intermediate' : '' ?>"><?= Html::encode(Yii::$app->formatter->asDate($day['date'],'d MMM')) ?></span>
<?php endif; endforeach ?>
</div></div>
<details class="mt-3"><summary class="activity-data-toggle"><?= Html::encode(UM::t('back', 'View daily data')) ?></summary>
<div class="table-responsive mt-3"><table class="table table-sm mb-0"><caption class="visually-hidden"><?= Html::encode(UM::t('back', 'Daily recorded logins')) ?></caption><thead><tr><th scope="col"><?= Html::encode(UM::t('back', 'Date')) ?></th><th scope="col"><?= Html::encode(UM::t('back', 'Recorded logins')) ?></th></tr></thead><tbody>
<?php foreach (array_reverse($activity['series']) as $day): ?><tr><td><?= Yii::$app->formatter->asDate($day['date']) ?></td><td><?= (int)$day['count'] ?></td></tr><?php endforeach ?>
</tbody></table></div>
</details>
<?php endif ?>
</div>
</section>
