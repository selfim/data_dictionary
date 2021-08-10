<?php
declare (strict_types=1);


namespace DictionaryExport;

use PDO;
class DataDict
{
    protected $db;
    public function __construct(array $config)
    {
        //'mysql:dbname='.$config['dbname'].';host='.$config['host']
        $this->db = new PDO($config['dsn'], $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->query('SET NAMES utf8');
    }
    public  function make(string $type = 'markdown')
    {
        switch ($type) {
            case 'html':
                return $this->generateHtml();
            case 'json':
                return $this->generateJson();
            default:
                return $this->generateMd();
        }
    }
    protected  function getTables()
    {
        $list = $tmp = [];
        $res = $this->db->query("SHOW TABLE STATUS");
        while ( $row =  $res->fetch(PDO::FETCH_ASSOC)) {
            //$tables [] ['TABLE_NAME'] = current($row);
            $tmp['name']    = $row['Name'];
            $tmp['comment'] = $row['Comment'];
            $tmp['engine']  = $row['Engine'];
            $list[]         = $tmp;
        }
        unset($tmp);
        return $list;
    }
    protected function getColumns(string $table)
    {
        if (empty($table)) {
            return false;
        }
        $list = $tmp = [];
        $columns = $this->db->query('SHOW FULL COLUMNS FROM ' . $table);
        while ( $row =  $columns->fetch(PDO::FETCH_ASSOC)) {
            $tmp['field']     = $row['Field'];
            $tmp['type']      = $row['Type'];
            $tmp['collation'] = $row['Collation'];
            $tmp['default']   = $row['Default'];
            $tmp['null']      = $row['Null'];
            $tmp['extra']     = $row['Extra'];
            $tmp['comment']   = $row['Comment'];
            $list[]           = $tmp;
        }
        unset($tmp);
        return $list;
    }
    protected  function generateHtml()
    {
        $tables   = $this->getTables();
        $html     = '<!DOCTYPE html>
<html lang="zh">
<head>
	<meta charset="UTF-8">
	<title>数据字典</title>
	<style>
		body, td, th{font-family:"宋体"; font-size:14px;}
		table, h1, p,.menu{width:960px;margin:0px auto;}
		table{border-collapse:collapse;border:1px solid #CCC;background:#efefef;}
		table caption{text-align:left; background-color:#fff; line-height:2em; font-size:14px; font-weight:bold; }
		table th{text-align:left; font-weight:bold;height:26px; line-height:26px; font-size:12px; border:1px solid #CCC;padding-left:5px;}
		table td{height:20px; font-size:12px; border:1px solid #CCC;background-color:#fff;padding-left:5px;}
		a{text-decoration:none}
		.w150{ width:150px;}
		.w80{ width:80px;}
		.w100{ width:100px;}
		.w300{ width:300px;}
	</style>
</head>
<body ><h1 style="text-align:center;">数据字典</h1>';
        $menu     = '<div class="menu">';
        $infoList = '';
        foreach ($tables as $k => $val) {
            $menu     .= '<a href="#' . $k . '">' . ($k + 1) . '……' . $val['name'] . '   ' . $val['comment'] . '</a> <br/>';
            $info     = $this->generateHtml($val['name']);
            $infoList .= '<table id="' . $k . '" border="1" cellspacing="0" cellpadding="0" align="center">';
            $infoList .= '<caption>表名：' . $val['name'] . '<span style="font-size: 12px;color:#9e9e9e">【' . $val['engine'] . '】</span>' . $val['comment'] . '</caption>';
            $infoList .= '<tbody><tr><th>字段名</th><th>数据类型</th><th>默认值</th><th>允许非空</th><th>自动递增</th><th>备注</th></tr>';
            $infoList .= '<br/>';
            foreach ($info as $item) {
                $infoList .= '<td class="w150">' . $item['field'] . '</td>';
                $infoList .= '<td class="w150">' . $item['type'] . '</td>';
                $infoList .= '<td class="w80">' . $item['default'] . '</td>';
                $infoList .= '<td class="w100">' . $item['null'] . '</td>';
                $infoList .= '<td class="w100">' . ($item['extra'] === 'auto_increment' ? '是' : ' ') . '</td>';
                $infoList .= '<td class="w300">' . $item['comment'] . '</td>';
                $infoList .= '</tr>';
            }
        }

        $html .= $menu . '</div>' . $infoList;
        $html .= '</tbody></table></p>';
        $html .= '<p style="text-align:left;margin:20px auto;">总共：' . count($tables) . '个数据表</p></body></html>';
        return $html;
    }
    protected  function generateJson()
    {
        $tables = $this->getTables();
        //循环所有表
        foreach ($tables as &$val) {
            $val['item'] = $this->getColumns($val['name']);
        }
        unset($val);
        return json_encode($tables);
    }
    protected  function generateMd()
    {
        $title  = '数据字典';
        $mark   = '';
        $tables = $this->getTables();
        //循环所有表
        foreach ($tables as $val) {
            $info = $this->getColumns($val['name']);
            $mark .= '## ' . $val['name'] . ' 【' . $val['engine'] . '】 ' . $val['comment'] . PHP_EOL;
            $mark .= '' . PHP_EOL;
            $mark .= '|  字段名  |  数据类型  |  默认值  |  允许非空  |  自动递增  |  备注  |' . PHP_EOL;
            $mark .= '| ------ | ------ | ------ | ------ | ------ | ------ |' . PHP_EOL;
            foreach ($info as $item) {
                $mark .= '| ' . $item['field'] . ' | ' . $item['type'] . ' | ' . $item['default'] . ' | ' . $item['null'] . ' | ' . ($item['extra'] == 'auto_increment' ? '是' : '') . ' | ' . (empty($item['comment']) ? '-' : str_replace('|', '/', $item['comment'])) . ' |' . PHP_EOL;
            }
            $mark .= '' . PHP_EOL;
        }
        //markdown输出
        $md_tplt = <<<EOT
# {$title}
>   本数据字典由PHP脚本自动导出,字典的备注来自数据库表及其字段的注释(`comment`).开发者在增改库表及其字段时,请在 `migration` 时写明注释,以备后来者查阅.

{$mark}
EOT;
        return $md_tplt;
    }
}