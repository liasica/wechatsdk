<?php
/**
 * Author: liasica
 * CreateTime: 15/5/25 下午12:32
 * Filename: WechatSDK.php
 * PhpStorm: v2
 */
namespace liasica\wechatsdk;

use yii\base\Component;
use Yii;

class WechatSDK extends Component
{
  public $token;
  public $appid;
  public $appsecret;
  public $EncodingAESKey;
  const WECHATAPI = 'https://api.weixin.qq.com/cgi-bin/';
  // ACCESS_TOKEN
  const ACCESSTOKENAPI = 'token?grant_type=client_credential&';
  // 设置行业
  const INDUSTRY = 'template/api_set_industry?';

  public function init()
  {
    if ($this->token === NULL)
    {
      throw new InvalidConfigException('必须设置token');
    }
    if ($this->appid === NULL)
    {
      throw new InvalidConfigException('必须设置appid');
    }
    if ($this->appsecret === NULL)
    {
      throw new InvalidConfigException('必须设置appsecret');
    }
  }

  /**
   * 调试信息
   *
   * @param      $data
   * @param bool $end
   */
  public function p($data, $end = FALSE)
  {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    $end && Yii::$app->end();
  }

  /**
   * curl获取数据||GET
   *
   * @param      $url
   * @param null $params
   *
   * @return mixed
   */
  public function getcurl($url, $params = NULL)
  {
    // curl 初始化
    $ch = curl_init();
    // 设置选项
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    //执行并获取HTML文档内容
    $output = curl_exec($ch);
    //释放curl句柄
    curl_close($ch);
    return $output;
  }

  /**
   * 微信curl post
   *
   * @param       $url
   * @param       $vars
   * @param null  $pem
   * @param int   $second
   * @param array $aHeader
   *
   * @return bool|mixed
   */
  public static function curl_post($url, $vars, $pem = NULL, $second = 30, $aHeader = array())
  {
    $ch = curl_init();
    //超时时间
    curl_setopt($ch, CURLOPT_TIMEOUT, $second);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //这里设置代理，如果有的话
    //curl_setopt($ch,CURLOPT_PROXY, '10.206.30.98');
    //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    // 证书
    if ($pem != NULL)
    {
      //以下两种方式需选择一种
      //第一种方法，cert 与 key 分别属于两个.pem文件
      //默认格式为PEM，可以注释
      curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
      curl_setopt($ch, CURLOPT_SSLCERT, $pem['sslcert']);
      //默认格式为PEM，可以注释
      curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
      curl_setopt($ch, CURLOPT_SSLKEY, $pem['sslkey']);
      curl_setopt($ch, CURLOPT_CAINFO, $pem['cainfo']);
      //第二种方式，两个文件合成一个.pem文件
      //curl_setopt($ch, CURLOPT_SSLCERT, getcwd() . '/all.pem');
    }
    if (count($aHeader) >= 1)
    {
      curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
    }
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
    $data = curl_exec($ch);
    if ($data)
    {
      curl_close($ch);
      return $data;
    }
    else
    {
      $error = curl_errno($ch);
      echo "call faild, errorCode:$error\n";
      curl_close($ch);
      return FALSE;
    }
  }

  /**
   * 获取ACCESSTOKEN
   *
   * @param bool $refresh
   *
   * @return mixed
   */
  public function getAccessToken($refresh = FALSE)
  {
    $session = Yii::$app->session;
    $token   = $session->get('wechatSDK-ACCESSTOKEN');
    if ($refresh || $token == NULL || time() > $session->get($token) + 6000)
    {
      $url   = self::WECHATAPI . self::ACCESSTOKENAPI . 'appid=' . $this->appid . '&secret=' . $this->appsecret;
      $res   = $this->getcurl($url);
      $res   = json_decode($res);
      $token = $res->access_token;
      $session->set('wechatSDK-ACCESSTOKEN', $token);
      $session->set($token, time());
    }
    return $token;
  }

  /**
   * 设置所属行业
   *
   * @param $industry_id1
   * @param $industry_id2
   *
   * @return bool|mixed
   */
  public function setIndustry($industry_id1, $industry_id2)
  {
    $access_token = $this->getAccessToken();
    $url          = self::WECHATAPI . self::INDUSTRY . 'access_token=' . $access_token;
    $vars         = [
      'industry_id1' => $industry_id1,
      'industry_id2' => $industry_id2
    ];
    $this->p(http_build_query($vars));
    $res = $this->curl_post($url, http_build_query($vars));
    $res = json_decode($res);
    return $res;
  }

  /**
   * 发送模板消息
   * @link         http://mp.weixin.qq.com/wiki/17/304c1885ea66dbedf7dc170d84999a9d.html
   *
   * @param        $touser
   * @param        $template_id
   * @param null   $url
   * @param string $topcolor
   * @param        $data
   *
   * @param bool   $refresh
   *
   * @return mixed
   */
  public function sendMessageTpl($touser, $template_id, $url = NULL, $topcolor = '#FF0000', $data, $refresh = FALSE)
  {
    $apiURL         = self::WECHATAPI . 'message/template/send?access_token=' . $this->getAccessToken($refresh);
    $toPost         = [
      'touser'      => $touser,
      'template_id' => $template_id,
      'topcolor'    => $topcolor,
      'url'         => $url
    ];
    $toPost['data'] = $data;
    $toPost         = json_encode($toPost);
    $return         = $this->curl_post($apiURL, $toPost);
    $return         = json_decode($return);
    if ($return->errcode == 40001)
    {
      $return = $this->sendMessageTpl($touser, $template_id, $url, $topcolor, $data, TRUE);
    }
    return $return;
  }
}
