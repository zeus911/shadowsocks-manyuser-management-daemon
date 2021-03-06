<?php
function shadowsockslibev_ConfigOptions()
{
    $configarray = array(   //后台看到的插件设置选项
        "用户数据库" => array("Type" => "text", "Size" => "32"),
        "用户表" => array("Type" => "text", "Size" => "32"),
        "用户组" => array("Type" => "text", "Size" => "32"),
        "起始端口" => array("Type" => "text", "Size" => "32"),
        "节点数据库" => array("Type" => "text", "Size" => "32"),
        "节点表" => array("Type" => "text", "Size" => "32"),
        "附加信息" => array("Type" => "text", "Size" => "32"),
    );
    return $configarray;
}

function shadowsockslibev_CreateNewPort($params)    //获得已有的最大的端口的值
{
    $userdatabase = $params['configoption1'];
    $usertable = $params['configoption2'];
    $portstart = $params['configoption4'];
    if (!isset($portstart) || $portstart == "") {
        $start = 10000;
    } else {
        $start = $portstart;
    }
    $end = 65535;
    $mysql = mysql_connect($params['serverip'], $params['serverusername'], $params['serverpassword']);
    if (!$mysql) {
        $result = "Can not connect to MySQL Server" . mysql_error();
    } else {
        mysql_select_db($userdatabase, $mysql);
        $select = mysql_query("SELECT port FROM " . $usertable);
        $select = mysql_fetch_array($select);
        if (!$select == "") {
            $get_last_port = mysql_query("SELECT port FROM " . $usertable . " order by port desc limit 1", $mysql);
            $get_last_port = mysql_fetch_array($get_last_port);
            $result = $get_last_port['port'] + 1;
            if ($result > $end) {
                $result = "Port exceeds the maximum value.";
            }
        } else {
            $result = $start;
        }
    }
    return $result;
}

function shadowsockslibev_CreateAccount($params)
{
    $serviceid = $params["serviceid"]; # Unique ID of the product/service in the WHMCS Database
    $password = $params["password"];
    $group = $params['configoption3'];
    $adminusername = mysql_fetch_array(mysql_query("SELECT username FROM `tbladmins`"));
    $port = shadowsockslibev_CreateNewPort($params);
    $userdatabase = $params['configoption1'];
    $usertable = $params['configoption2'];

    $mysql = mysql_connect($params['serverip'], $params['serverusername'], $params['serverpassword']);
    if (!$mysql) {
        $result = "Can not connect to MySQL Server" . mysql_error();
    } else {
        mysql_select_db($userdatabase, $mysql);
        $select = mysql_query("SELECT pid FROM " . $usertable . " WHERE pid='" . $serviceid . "'", $mysql);
        $select = mysql_fetch_array($select);
        if (!empty($select['pid'])) {
            $result = "Service already exists.";
        } else {    //create system account
            if (isset($params['customfields']['password'])) {
                $command = "encryptpassword";
                $adminuser = $adminusername['username'];
                $values["password2"] = $params["customfields"]['password'];
                $results = localAPI($command, $values, $adminuser);
                $table = "tblhosting";
                $update = array("password" => $results['password']);
                $where = array("id" => $params["serviceid"]);
                update_query($table, $update, $where);
                $password = $params["customfields"]['password'];
            }   //create module account
            $query = mysql_query("INSERT INTO " . "`" . $usertable . "`" . " (port,passwd,`group`,pid) VALUES ('" . $port . "','" . $password . "','" . $group . "','" . $serviceid . "')", $mysql);
            if ($query) {
                $result = "success";
            } else {
                $result = mysql_error();
            }
        }
    }
    return $result;
}

function shadowsockslibev_SuspendAccount($params)
{
    $userdatabase = $params['configoption1'];
    $usertable = $params['configoption2'];
    $mysql = mysql_connect($params['serverip'], $params['serverusername'], $params['serverpassword']);
    if (!$mysql) {
        $result = "Can not connect to MySQL Server" . mysql_error();
    } else {
        mysql_select_db($userdatabase, $mysql);
        $select = mysql_query("SELECT pid FROM " . $usertable . " WHERE pid='" . $params['serviceid'] . "'", $mysql);
        $select = mysql_fetch_array($select);
        if ($select == "") {
            $result = "Can't find.";
        } else {
            if (mysql_query("UPDATE " . $usertable . " SET  enable=0 WHERE pid='" . $params['serviceid'] . "'", $mysql)) {
                $result = 'success';
            } else {
                $result = "Can't suspend user." . mysql_error();
            }
        }
    }
    return $result;
}

function shadowsockslibev_UnSuspendAccount($params)
{
    $userdatabase = $params['configoption1'];
    $usertable = $params['configoption2'];
    $mysql = mysql_connect($params['serverip'], $params['serverusername'], $params['serverpassword']);
    if (!$mysql) {
        $result = "Can not connect to MySQL Server" . mysql_error();
    } else {
        mysql_select_db($userdatabase, $mysql);
        $select = mysql_query("SELECT pid FROM " . $usertable . " WHERE pid='" . $params['serviceid'] . "'", $mysql);
        $select = mysql_fetch_array($select);
        if ($select == "") {
            $result = "Can't find.";
        } else {
            if (mysql_query("UPDATE " . $usertable . " SET  enable=1 WHERE pid='" . $params['serviceid'] . "'", $mysql)) {
                $result = 'success';
            } else {
                $result = "Can't suspend user." . mysql_error();
            }
        }
    }
    return $result;
}

function shadowsockslibev_TerminateAccount($params)
{
    $userdatabase = $params['configoption1'];
    $usertable = $params['configoption2'];
    $mysql = mysql_connect($params['serverip'], $params['serverusername'], $params['serverpassword']);
    if (!$mysql) {
        $result = "Can not connect to MySQL Server" . mysql_error();
    } else {
        mysql_select_db($userdatabase, $mysql);
        if (mysql_query("DELETE FROM " . $usertable . " WHERE pid='" . $params['serviceid'] . "'", $mysql)) {
            $result = 'success';
        } else {
            $result = 'Error. Cloud not Terminate this Account.' . mysql_error();
        }
    }
    return $result;
}

//TODO funciton Renew

function shadowsockslibev_ChangePassword($params)
{
    $userdatabase = $params['configoption1'];
    $usertable = $params['configoption2'];
    $mysql = mysql_connect($params['serverip'], $params['serverusername'], $params['serverpassword']);
    if (!$mysql) {
        $result = "Can not connect to MySQL Server" . mysql_error();
    } else {
        mysql_select_db($userdatabase, $mysql);
        $select = mysql_query("SELECT pid FROM " . $usertable . " WHERE pid='" . $params['serviceid'] . "'", $mysql);
        $select = mysql_fetch_array($select);
        if ($select == "") {
            $result = "Can't find.";
        } else {
            if (mysql_query("UPDATE " . $usertable . " SET passwd='" . $params['password'] . "' WHERE pid='" . $params['serviceid'] . "'")) {
                $table = "tblcustomfields";
                $fields = "id";
                $where = array("fieldname" => "password|Password");
                $result = select_query($table, $fields, $where);
                $data = mysql_fetch_array($result);
                $fieldid = $data['id'];
                $table = "tblcustomfieldsvalues";
                $update = array("value" => $params["password"]);
                $where = array("fieldid" => $fieldid, "relid" => $params["serviceid"]);
                update_query($table, $update, $where);
                $result = 'success';
            } else {
                $result = 'Error' . mysql_error();
            }
        }
    }
    return $result;
}

//TODO function ChangePackage

function shadowsockslibev_ClientArea($params)
{
    $userdatabase = $params['configoption1'];
    $usertable = $params['configoption2'];
    $group = $params['configoption3'];
    $nodedatabase = $params['configoption5'];
    $nodetable = $params['configoption6'];
    $additioninfo = $params['configoption7'];
    $mysql = mysql_connect($params['serverip'], $params['serverusername'], $params['serverpassword']);
    if (!$mysql)
        return mysql_error();
    if (!mysql_select_db($userdatabase, $mysql))
        return "userdatabase not found";
    $query = mysql_query("SELECT port,passwd,encrypt_method,udp_relay,fast_open FROM " . $usertable . " WHERE pid='" . $params['serviceid'] . "'", $mysql);
    $query = mysql_fetch_array($query);
    if ($query == "")
        return "serviceid not found,pid={$params['serviceid']}";
    $port = $query['port'];
    $passwd = $query['passwd'];
    $encrypt_method = $query['encrypt_method'];
    $udp_relay = $query['udp_relay'];
    if ($udp_relay) {
        $udp_relay = "ON";
    } else {
        $udp_relay = "OFF";
    }
    $fast_open = $query['fast_open'];
    if ($fast_open) {
        $fast_open = "ON";
    } else {
        $fast_open = "OFF";
    }
    $html = "
        <div style=\"text-align:center;\">
            <strong><span style=\"font-size:18px;\">连接信息</span></strong>
            <table style=\"width:100%;\" cellpadding=\"2\" cellspacing=\"0\" align=\"center\" border=\"0\" bordercolor=\"#000000\" class=\"ke-zeroborder\">
                <tbody>
                    <tr>
                        <td style=\"text-align:center;\">
                            端口
                        </td>
                        <td style=\"text-align:center;\">
                            {$port}
                        </td>
                    </tr>
                    <tr>
                        <td style=\"text-align:center;\">
                            密钥
                        </td>
                        <td style=\"text-align:center;\">
                            {$passwd}
                        </td>
                    </tr>
                    <tr>
                        <td style=\"text-align:center;\">
                            加密方式
                        </td>
                        <td style=\"text-align:center;\">
                            {$encrypt_method}
                        </td>
                    </tr>
                    <tr>
                        <td style=\"text-align:center;\">
                            udp_relay
                        </td>
                        <td style=\"text-align:center;\">
                            {$udp_relay}
                        </td>
                    </tr>
                    <tr>
                        <td style=\"text-align:center;\">
                            fast_open
                        </td>
                        <td style=\"text-align:center;\">
                            {$fast_open}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        </br>
        <div style=\"text-align:center;\">
            <strong><span style=\"font-size:18px;\">服务器信息</span></strong>
            <p>
            </p>
            <table style=\"text-align:center;width:100%;\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class=\"ke-zeroborder\" bordercolor=\"#000000\">
            <tbody>
                <tr>
                    <td style=\"text-align:center;\">
                        IP
                    </td>
                    <td style=\"text-align:center;\">
                        域名
                    </td>
                    <td style=\"text-align:center;\">
                        地区
                    </td>
                    <td style=\"text-align:center;\">
                        操作系统
                    </td>
                    <td style=\"text-align:center;\">
                        平均网络负载(5min,10min,15min)
                    </td>
                    <td style=\"text-align:center;\">
                        备注
                    </td>
                </tr>
    ";
    if (!mysql_select_db($nodedatabase, $mysql))
        return "nodedatabase not found";
    $query = mysql_query("SELECT ip,domain,area,os,net_load_5min,net_load_10min,net_load_15min,remark FROM " . $nodetable . " WHERE `group`='" . $group . "' ORDER BY `order`", $mysql);
    $result = mysql_fetch_array($query);
    if ($result == "") {
        return "no server available";
    } else {
        do {
            $net_load_5min = round($result['net_load_5min'] / 1024, 2);
            $net_load_10min = round($result['net_load_10min'] / 1024, 2);
            $net_load_15min = round($result['net_load_15min'] / 1024, 2);
            $html = $html . "
                <tr>
                    <td style=\"text-align:center;\">
                        {$result['ip']}
                    </td>
                    <td style=\"text-align:center;\">
                        {$result['domain']}
                    </td>
                    <td style=\"text-align:center;\">
                        {$result['area']}
                    </td>
                    <td style=\"text-align:center;\">
                        {$result['os']}
                    </td>
                    <td style=\"text-align:center;\">
                        {$net_load_5min} KB/S&nbsp; &nbsp; &nbsp;{$net_load_10min} KB/S&nbsp; &nbsp; &nbsp;{$net_load_15min} KB/S
                    </td>
                    <td style=\"text-align:center;\">
                        {$result['remark']}
                    </td>
                </tr>
            ";
        } while ($result = mysql_fetch_array($query));
    }
    $html = $html . "
            </tbody>
        </table>
        </div>
        </br>
    ";
    $html = $html . "
    <div style=\"text-align:center;\">
        <strong><span style=\"font-size:18px;\">附加信息</span></strong>
        <p>
        </p>
        ${additioninfo}
    </div>
    ";
    return $html;
}

//TODO function AdminArea

//TODO funciton LoginLink

//TODO function ClientAreaCustomButtonArray

//TODO function ClientAreaAllowedFunctions

//TODO function AdminCustomButtonArray

//TODO function UsageUpdate

function shadowsockslibev_AdminServicesTabFields($params)
{
    $userdatabase = $params['configoption1'];
    $usertable = $params['configoption2'];
    $serviceid = $params['serviceid'];

    $mysql = mysql_connect($params['serverip'], $params['serverusername'], $params['serverpassword']);
    if (!$mysql)
        return array('status' => 'Cannot connect to mysql');
    mysql_select_db($userdatabase, $mysql);
    $query = mysql_query("SELECT * FROM `" . $usertable . "` WHERE `pid` = " . $serviceid, $mysql);
    $result = mysql_fetch_array($query);
    if (!$result)
        return array('status' => 'Service not found');

    $fieldsarray = array(
        'id' => $result['id'],
        'port' => $result['port'],
        'passwd' => $result['passwd'],
        'encrypt_method' => $result['encrypt_method'],
        'udp_relay' => $result['udp_relay'],
        'fast_open' => $result['fast_open'],
        'group' => $result['group'],
        'enable' => $result['enable'],
        'serviceid' => $result['pid'],
    );

    return $fieldsarray;
}

//TODO AdminServicesTabFieldsSave
