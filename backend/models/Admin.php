<?php
namespace backend\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * This is the model class for table "yii2_admin".
 *
 * @property integer $id
 * @property string $username
 * @property string $password
 * @property string $status
 * @property string $password_reset_token
 */
class Admin extends ActiveRecord implements IdentityInterface
{
    const SUPER_ADMIN     = '超级管理员';
    const FINANCIAL_STAFF = '财务';

    public $password;

    const STATUS_DELETE = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_SOFT_DELETE = 2;

    public static $statusTexts = [
        self::STATUS_DELETE => '禁用',
        self::STATUS_ACTIVE => '启用',
        self::STATUS_SOFT_DELETE => '软删除',
    ];

    public static $statusStyles = [
        self::STATUS_DELETE => 'label-warning',
        self::STATUS_ACTIVE => 'label-info',
        self::STATUS_SOFT_DELETE => 'label-default',
    ];

    public static function tableName() {
        return '{{%admin}}';
    }

    public function behaviors() {
        return [
            TimestampBehavior::className(),
        ];
    }

    public function rules() {
        return [
            [['username', 'email'], 'required'],
            [['username'], 'string', 'length' => [6, 15]],
            [['password'], 'required', 'on' => ['create']],
            [['reg_ip', 'last_login_time', 'last_login_ip'], 'integer'],
            [['auth_key'], 'string', 'length' => 32],
            [['username', 'email'], 'unique'],
            [['email'], 'email'],
            ['password_reset_token','safe'],
            ['status', 'default', 'value' => self::STATUS_ACTIVE],
            ['status', 'in', 'range' => [
                self::STATUS_ACTIVE, self::STATUS_DELETE
            ]],
        ];
    }

    public function scenarios() {
        $scenarios = parent::scenarios();
        $scenarios['create'] = ['username', 'email', 'password', 'status'];
        $scenarios['update'] = ['username', 'email', 'password', 'status'];
        return $scenarios;
    }

    public function attributeLabels() {
        return [
            'id' => 'ID',
            'username' => '用户名',
            'auth_key' => 'Auth Key',
            'password' => '密码',
            'password_hash' => 'Password Hash',
            'email' => '邮箱',
            'reg_ip' => '注册IP',
            'last_login_time' => '最后登录时间',
            'last_login_ip' => '最后登录IP',
            'status' => '状态',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
            'user_role' => '所属角色',
            'role' => '所属角色组'
        ];
    }

    /**
     * 获取账号信息
     * @param  [int] $id [后台用户id]
     */
    public static function findIdentity($id) {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    public static function findIdentityByAccessToken($token, $type = null) {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    /**
     * 获取账号信息
     * @param  [string] $username [用户名]
     */
    public static function findByUsername($username) {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * 获取账号主键值(id)
     */
    public function getId() {
        return $this->getPrimaryKey();
    }

    /**
     * 获取账号auth_key值
     */
    public function getAuthKey() {
        return $this->auth_key;
    }

    /**
     * 验证auth_key
     * @param  [string] $authKey [auth key]
     */
    public function validateAuthKey($authKey) {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * 验证用户密码
     * @param  [string] $password [用户密码]
     */
    public function validatePassword($password) {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * 生成加密后的密码
     * @param [string] $password [用户密码]
     */
    public function setPassword($password) {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * 生成auth_key
     * @return [type] [description]
     */
    public function generateAuthKey() {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * 获取账号状态
     */
    public function getStatus() {
        return self::$statusTexts[$this->status];
    }

    /**
     * 获取账号状态样式
     */
    public function getStatusStyle() {
        return self::$statusStyles[$this->status];
    }

    /**
     * 获取状态
     */
    public function getStatusTexts() {
        return self::$statusTexts;
    }

    //获取用户所在的用户组
    public function getGroup() {
        return implode(',', array_keys(Yii::$app->authManager->getRolesByUser($this->id)));
    }

    public function getRole()
    {
        $role = Yii::$app->authManager->getRolesByUser($this->id);
        if ($role) {
            $role = current($role)->name;
        } else {
            $role = "未指定角色";
        }

        return $role;
    }

    public function beforeSave($insert) {
        if($this->isNewRecord) {
            $this->generateAuthKey();
            $this->reg_ip = ip2long(Yii::$app->getRequest()->getUserIP());
            $this->last_login_time = 0;
            $this->last_login_ip = 0;
        }
        if(!empty($this->password)) $this->setPassword($this->password);
        return parent::beforeSave($insert);
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return bool
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int) substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        return $timestamp + $expire >= time();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
    }


    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token)
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            'status' => self::STATUS_ACTIVE,
        ]);
    }
}
