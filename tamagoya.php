<?php
require_once dirname( __FILE__ ) . '/goutte.phar';
use Goutte\Client;
use \Symfony\Component\DomCrawler\Crawler;
date_default_timezone_set('Asia/Tokyo');
if ( count($argv) < 2 ) {
  echo "第一引数にチャットワークAPIのトークンを第二引数にルームIDを指定してください。\n尚、第三引数に数値を指定すると指定した数値分加減した日付のメニューが対象になります。\n";
  return;
}
//var_dump($argv);exit;
$token = $argv[1];
$room_id = $argv[2];
$add = empty($argv[3]) ? 0 : $argv[3];
$daynum = date('j', strtotime("+$add day"));
//var_dump($daynum);
$result = '';

$body = file_get_contents('http://www.tamagoya.co.jp/menu.html');
$template = file_get_contents(dirname( __FILE__ ) . '/template.html');
$html = sprintf($template, $body);
$crawler = new Crawler($html);
$index = 0;
$crawler->filter('.menutitle_date')->each(function ($date) use ($daynum, &$index) 
{
  static $continue = false;
  $str = $date->text();
  // 以下1行は月またぎ対応
  $str = preg_replace('/(.+月)(.+日)/', '$2', $str);
  $tmp = (int)substr($str, 0, 2);
  //var_dump($tmp);
  if ( (int)$daynum == (int)$tmp || $continue ) {
    $continue = true;
    return;
  }
  $index++;
});
//echo $index . "\n";
$arrayWeek = array('日','月','火','水','木','金','土');
var_dump( date('w', strtotime("+$add day")) );
$result = "[info][title]" . date('n/j', strtotime("+$add day")) . '(' . $arrayWeek[ date('w', strtotime("+$add day")) ] . ")のメニュー[/title]";
//var_dump($result);exit;
if ( $index >= 5 ) {
  $result .= "すまんが今日はお休みのようじゃ＼(^o^)／";
  $result .= "[/info]";
  $message = urlencode( $result );
  # チャットワークAPIを実行
  shell_exec('curl -X POST -H "X-ChatWorkToken: ' . $token . '" -d "body=' . $message . '" "https://api.chatwork.com/v2/rooms/' . $room_id . '/messages"');
  return;
}
$week = $crawler->filter('.week_menulist')->eq($index);
$week->filter('li')->each(function ($li) use (&$result) {
  $brCnt = $li->filter('br')->count();
  if ( $brCnt > 0 ) {
    $str = $li->getNode(0)->ownerDocument->saveHTML($li->getNode(0));
    $str = str_replace('<li class="menu_maindish">', '', $str);
    $str = str_replace('</li>', '', $str);
    $str = str_replace(' ', '', $str);
    $str = str_replace('　', '', $str);
    $array = explode('<br>', $str);
    foreach($array as $val) $result .= $val . "\n";
    $result .= "[hr]";
  } else {
    $result .= $li->text() . "\n";
  }
});
$result .= "[hr]";
$calorie = $crawler->filter('.menu_calorie')->eq($index);
if ($calorie->filter('br')->count() > 0) {
  $str = $calorie->getNode(0)->ownerDocument->saveHTML($calorie->getNode(0));
  $str = str_replace('<p class="menu_calorie">', '', $str);
  $str = str_replace('</p>', '', $str);
  $str = str_replace(' ', '', $str);
  $str = str_replace('　', '', $str);
  $array = explode('<br>', $str);
  foreach($array as $val) $result .= $val . "\n";
} else {
  $result .= $calorie->text();
}
$result .= "[/info]";
$result = "【(h)たまご屋注文する方募集中(h)】\n今週もたまご屋dayがやってきました(F)\nどしどしご注文ください(*)" . $result;
$message = urlencode( $result );
# チャットワークAPIを実行
shell_exec('curl -X POST -H "X-ChatWorkToken: ' . $token . '" -d "body=' . $message . '" "https://api.chatwork.com/v2/rooms/' . $room_id . '/messages"');
