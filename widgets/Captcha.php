<?php
namespace webvimark\modules\UserManagement\widgets;

use yii\helpers\Html;
use yii\helpers\Json;
use webvimark\modules\UserManagement\UserManagementModule as UM;

class Captcha extends \yii\captcha\Captcha
{
    public $captchaAction = ['/user-management/auth/captcha'];

    public function init()
    {
        parent::init();
        $this->imageOptions = array_merge(['class' => 'um-captcha-image', 'alt' => UM::t('front', 'Verification code')], $this->imageOptions);
        $this->options = array_merge(['class' => 'form-control', 'autocomplete' => 'off', 'spellcheck' => 'false', 'placeholder' => UM::t('front', 'Enter the code')], $this->options);
        $refresh = Html::button(Html::encode(UM::t('front', 'New code')), ['type' => 'button', 'id' => $this->options['id'] . '-refresh', 'class' => 'btn btn-secondary btn-sm', 'aria-controls' => $this->imageOptions['id']]);
        $this->template = '<div class="um-captcha"><div class="um-captcha-challenge">{image}' . $refresh . '</div>{input}</div>';
    }

    public function registerClientScript()
    {
        parent::registerClientScript();
        $button = Json::htmlEncode('#' . $this->options['id'] . '-refresh');
        $image = Json::htmlEncode('#' . $this->imageOptions['id']);
        $this->view->registerJs("jQuery($button).on('click', function () { jQuery($image).trigger('click'); });");
    }
}
