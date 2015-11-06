<?php

require_once("header.inc");
require_once("../config.inc.php");

function pointcrud($inp, $owner_uid, $admin) {
		$db=get_conn();


	switch($inp['action']) {

		case 'list':
			$where=array();
			if (isset($inp['id']) && !empty($inp['id'])) {
				$where[] = "id = " . $inp['id'];
			}
			if ($admin != 1 )  {
				$where[] = sprintf(" owner = %d", $owner_uid);
			}
			// pending approval
			if (isset($inp['contribute']) && $inp['contribute'] == 1 ) {
				$where[] = "contribute=1";
			}
			$where_str = (count($where)>0)? "WHERE ".implode(" AND ",$where) : "";
			$sql = sprintf("SELECT id,name,alias,type,class,number,status,ele,mt100,checked,comment,ST_X(coord) AS y,ST_Y(coord) as x,owner,contribute FROM point2 %s ORDER BY %s OFFSET %d LIMIT %d", 
			$where_str, $inp['jtSorting'], $inp['jtStartIndex'], $inp['jtPageSize']);
			$db->SetFetchMode(ADODB_FETCH_ASSOC);
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail sql: $sql";
			}
			break;
		case 'create':
			$sql = sprintf("SELECT count(*) as count FROM point2 WHERE owner=%d",$owner_uid);
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail $sql" . $db->ErrorMsg();
			}
			// 限制最多點數
			if ($rs[0]['count'] >= 100 ) {
				$errmsg[] = "too many rows: limit 100";
				break;
			}
			if (isset($inp['checked']))
				$checked = 1; 
			else
				$checked = 0;
			if (isset($inp['contribute']))
				$contribute = 1; 
			else
				$contribute = 0;
			$pp = sprintf("ST_GeomFromText('SRID=4326;POINT(%f %f)')",$inp['y'],$inp['x']);
			$inp['number'] = (empty($inp['number']))? "NULL": intval($inp['number']);
			$inp['ele'] = (empty($inp['ele']))? "NULL": intval($inp['ele']);
			$sql = sprintf("insert into point2 (id, name,alias,type,class,number,status,ele,mt100,checked,comment,coord,owner,contribute) values ( DEFAULT, '%s','%s','%s','%s',%s
				,'%s', %s,'%s','%s','%s',%s, %d, %d) returning id",
					$inp['name'],$inp['alias'],$inp['type'],$inp['class'], $inp['number'],
					$inp['status'],$inp['ele'],$inp['mt100'],$checked,pg_escape_string($inp['comment']),$pp, $owner_uid, $contribute );
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail $sql" . $db->ErrorMsg();
			}
			$newid = $rs[0]['id'];
			$sql = sprintf("SELECT id,name,alias,type,class,number,status,ele,mt100,checked,comment,ST_X(coord) AS y,ST_Y(coord) AS x,contribute,owner FROM point2 WHERE id=%d AND owner=%d",$newid, $owner_uid);
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail $sql" . $db->ErrorMsg();
			}
			break;
		case 'update':
			if (isset($inp['checked']) && $inp['checked'] == 1)
				$checked = 1; 
			else
				$checked = 0;
			if (isset($inp['contribute']))
				$contribute = 1; 
			else
				$contribute = 0;
			$pp = sprintf("ST_GeomFromText('SRID=4326;POINT(%f %f)')",$inp['y'],$inp['x']);
			$inp['number'] = (empty($inp['number']))? "NULL": intval($inp['number']);
			$inp['ele'] = (empty($inp['ele']))? "NULL": intval($inp['ele']);
			// 1. 檢查身份
			$sql = sprintf("select owner from point2 where id=%d",$inp['id']);
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail $sql" . $db->ErrorMsg();
			}
			$point_owner_id = $rs[0]['owner'];
			if (!$admin) {
				if ($owner_uid != $point_owner_id) {
					$errmsg[] = "not owner";
					break;
				}
				$sql = sprintf("update point2 set name='%s',alias='%s',type='%s',class='%s',number=%s,status='%s',ele=%s,mt100='%s',checked='%s',comment='%s',coord=%s,contribute='%s' WHERE id=%s and owner=%d",
						$inp['name'],$inp['alias'],$inp['type'],$inp['class'], $inp['number'],
						$inp['status'],$inp['ele'],$inp['mt100'],$checked,pg_escape_string($inp['comment']),$pp,$contribute, $inp['id'], $owner_uid );

			} else {
				$sql = sprintf("update point2 set name='%s',alias='%s',type='%s',class='%s',number=%s,status='%s',ele=%s,mt100='%s',checked='%s',comment='%s',coord=%s,contribute='%s',owner=%d WHERE id=%s",
						$inp['name'],$inp['alias'],$inp['type'],$inp['class'], $inp['number'],
						$inp['status'],$inp['ele'],$inp['mt100'],$checked,pg_escape_string($inp['comment']),$pp,$contribute, $inp['owner'],$inp['id']);
			}
			if (($rs = $db->Execute($sql)) === false) {
				$errmsg[] = "fail sql: $sql";
			}
			$sql = sprintf("SELECT id,name,alias,type,class,number,status,ele,mt100,checked,comment,ST_X(coord) AS y,ST_Y(coord) as x,owner,contribute FROM point2 WHERE id=%d",$inp['id']);
			if (($rs = $db->GetAll($sql)) === false) {
				$errmsg[] = "fail $sql" . $db->ErrorMsg();
			}

			break;
		case 'delete':
			if (!$admin) 
				$sql = sprintf("DELETE from point2 WHERE id=%d and owner=%d",$inp['id'],$owner_uid);
			else
				$sql = sprintf("DELETE from point2 WHERE id=%d",$inp['id']);
			if (($rs = $db->Execute($sql)) === false) {
				$errmsg[] = "fail sql: $sql";
			}
			break;


	}
	if (count($errmsg) > 0 ) {
		$jTableResult = array();
		$jTableResult['Result'] = "ERROR";
		$jTableResult['Message'] = implode("|",$errmsg);
		print json_encode($jTableResult);
		return;

	}

	$jTableResult = array();
	$jTableResult['Result'] = "OK";
	if ($inp['action'] == 'create' || $inp['action'] == 'update')
		$jTableResult['Record'] = $rs[0];
	else
		$jTableResult['Records'] = $rs;
	print json_encode($jTableResult);



}
list($st, $uid) = userid();
if ($st === true)
	pointcrud($_REQUEST, $uid, is_admin());
	else
	header("Location: ". $site_html_root . "/login.php");
