<?
/*добавить везде проверку пользователя */

class Cash {
  private $db;
  private $usr;

  public function __construct($_db, $_usr) {
    $this->db = $_db;
    $this->usr = $_usr;
  }

  protected function makeFilter($name, $name_str, $val, $is_int, $not) {
    $f = "";
    if(empty($val) || $val == "null" || $val == "undefined") return $f;

    $no = "";
    $nos = "";
    if($not) {
      $nos = "NOT";
      $no = "!";
    }
    if($is_int) {
      $f = " AND ".$name." ".$no."= ". $val;
    } else {
      $f = " AND UPPER(".$name_str.") ".$nos." like UPPER('%". $this->db->escape($val)."%')";
    }
    return $f;
  }

  public function getExFilter($f) {
    $ret = "";

    if(intval($f['exfilter']) == 0) return $ret;

    $ret .= $this->makeFilter("c.nmcl_id", "cn.name", $f['nmcl_id'], intval($f['nmcl_id']), $f['nmcl_id_no'] );
    $ret .= $this->makeFilter("c.`group`", "cg.name", $f['pt_id'], intval($f['pt_id']), $f['pt_id_no'] );
    if( floatval($f['price_from']) != 0 ) $ret .= " AND c.price >= ".floatval($f['price_from']);
    if( floatval($f['price_to']) != 0 ) $ret .= " AND c.price <= ".floatval($f['price_to']);
    $ret .= $this->makeFilter("c.cash_type_id", "ct.name", $f['ctype_id'], intval($f['ctype_id']), 0 );
    $ret .= $this->makeFilter("c.cur_id", "cr.name", $f['cur_id'], intval($f['cur_id']), 0 );
    if($f['oper_id'] === "0") $ret .= " AND c.type = 0";
    if($f['oper_id'] === "1") $ret .= " AND c.type = 1";
    //$ret .= $this->makeFilter("c.type", "", $f['oper_id'], 1, 0 );
    $ret .= $this->makeFilter("c.org_id", "co.name", $f['org_id'], intval($f['org_id']), $f['org_id_no'] );
    $ret .= $this->makeFilter("", "c.note", $f['c.note'], 0, $f['note_no'] );
    if( intval($f['del']) == 1 ) {
      $ret .= " AND c.visible = 0 ";
    } else {
      $ret .= " AND c.visible = 1 ";
    }
    if( intval($f['file']) == 1 ) {
      $ret .= " AND c.file <> '' "; //IS NOT NULL ???
    }

    //echo $ret;
    return $ret;
  }

  public function getList($from, $to, $exfltr) {
    if(!$this->usr->canRead()) return array();

    $filter = "";

    if(empty($from)) $from = date("Y-m-d");
    if(empty($to)) $to = date("Y-m-d");

    $filter = $this->getExFilter($exfltr);
    if(empty($filter)) $filter = " AND c.visible = 1 ";

    //INDEXED BY XIF_CASHES_DBV
    $sql =
    " SELECT
      c.id, c.nmcl_id, cn.name as nom, c.`group`, cg.name gname, c.price, c.qnt, c.date as oper_date, datetime(c.date_edit, 'localtime') date_edit,
      c.org_id, co.name as oname, c.type, c.note, c.file, c.uid, cr.rate, cr.sign, c.cash_type_id, ct.name as cash_type,
      CASE WHEN c.type = 0 THEN -1 ELSE 1 END * c.price * c.qnt * cr.rate as amount
     FROM cashes c
     INNER JOIN currency cr
      ON(c.cur_id = cr.id)
     INNER JOIN cashes_nom cn
      ON(c.nmcl_id = cn.id)
     INNER JOIN cashes_org co
      ON(c.org_id = co.id)
     INNER JOIN cashes_type ct
      ON(c.cash_type_id = ct.id)
     INNER JOIN cashes_group cg
      ON(cg.id = c.`group`)
     WHERE
      c.date BETWEEN ? AND ?
      AND c.bd_id = ?
      ". $filter ."
     ORDER BY
      c.date, c.date_edit";

     return $this->db->select($sql, $from, $to, $this->usr->db_id);
  }

  public function getItem($id) {
    if(!$this->usr->canRead()) return array();

    $sql =
    " SELECT
      c.nmcl_id, c.`group`, c.price, c.qnt, c.date as oper_date, c.cur_id,
      c.org_id, c.type, c.note, c.file, c.uid, c.cash_type_id, c.type
     FROM cashes c
     WHERE
      c.id = ?
      AND c.bd_id = ?";

     return $this->db->line($sql, $id, $this->usr->db_id);
  }

  public function nmcl_list($query, $id) {
    $id = intval($id);
    if(empty($query) && $id == 0) return array();
    if(!$this->usr->canRead()) return array();

    $filter = " AND UPPER(cn.name) like UPPER('%". $this->db->escape($query) ."%') ";
    if($id > 0) {
      $filter = " AND c.id = ". $id;
    }

    $sql =
    "SELECT
	cn.id, cn.name
      FROM
	cashes c
      INNER JOIN cashes_nom cn
	ON(cn.id = c.nmcl_id)
      WHERE
	c.bd_id = ? AND c.visible = 1
	" . $filter . "
      GROUP BY
	cn.id, cn.name
      ORDER BY
	COUNT(1) DESC, cn.id";
    return $this->db->select($sql, $this->usr->db_id);
  }

  public function nmcl_param($nmcl_id) {
    if(!$this->usr->canRead()) return array();

    //cn.id, cn.name,
    $sql =
    "SELECT
      c.`group` grp,
      c.org_id,
      cn.name as org_name
    FROM
      cashes c
    INNER JOIN cashes_nom cn
    WHERE
      c.nmcl_id = ?
      AND c.bd_id = ? AND c.visible = 1
      AND cn.id = c.nmcl_id
    GROUP BY c.`group`, c.org_id
    ORDER BY
      COUNT(1) DESC
      limit 1 ";
    return $this->db->line($sql, $nmcl_id, $this->usr->db_id);
  }

  public function prod_type_list() {
    if(!$this->usr->canRead()) return array();

    $sql =
    "SELECT id,name FROM cashes_group";
    return $this->db->select($sql);
  }

  public function currency_list() {
    if(!$this->usr->canRead()) return array();

    $sql =
    "SELECT id,name FROM currency";
    return $this->db->select($sql);
  }

  public function cashes_type_list() {
    if(!$this->usr->canRead()) return array();

    $sql =
    "SELECT id,name FROM cashes_type";
    return $this->db->select($sql);
  }

  public function org_list($query, $id) {
    $id = intval($id);
    if(empty($query) && $id == 0) return array();
    if(!$this->usr->canRead()) return array();

    $filter = " AND UPPER(co.name) like UPPER('%". $this->db->escape($query) ."%') ";
    if($id > 0) {
      $filter = " AND c.id = ". $id;
    }

    $sql =
    "SELECT
      co.id, co.name
    FROM
      cashes c
    INNER JOIN cashes_org co
      ON(co.id = c.org_id)
    WHERE
      c.bd_id = ? AND c.visible = 1
      " . $filter . "
    GROUP BY
      co.id, co.name
    ORDER BY
      COUNT( 1 )  DESC, co.id ";
    return $this->db->select($sql, $this->usr->db_id);
  }

  public function del($id) {
    if(!$this->usr->canWrite()) return "Ошибка доступа";

    $this->db->start_tran();
    $fl = $this->getFile($id);
    if(!empty($fl)) {
      @unlink(__DIR__."/".$fl);
    }
    $this->db->exec("UPDATE cashes SET visible = 0 WHERE id = ? AND bd_id = ?", $id, $this->usr->db_id );
    $this->db->commit();
  }

  protected function add_refbook($name, $ref) {
    if(!$this->usr->canWrite()) return NULL;

    if(intval($name) > 0) return $name;

    $ref_id = 0;
    $ref_id = $this->db->element("SELECT MAX(id) id from ".$ref." WHERE name = ?", $name );
    $ref_id = intval($ref_id);

    if($ref_id == 0) {
      $this->db->exec("INSERT INTO ".$ref."(name) VALUES(?)",  $name);
      $ref_id = $this->db->last_id();
    }
    return intval( $ref_id );
  }

  protected function refbook_check($data, $files = array() ) {

    $ret = array();

    if(empty($data) || !$this->usr->canWrite()) return array('failure'=>true, 'msg'=> 'Нет данных');

    $ret['file'] = '';
    if(is_array($files['cash_item_file'])) {
      $ext = pathinfo($files['cash_item_file']['name'], PATHINFO_EXTENSION);
      $fname = 'files/'.crc32(time().$files['cash_item_file']['name']).".".$ext;
      if(move_uploaded_file($files['cash_item_file']['tmp_name'], "../".$fname)) {
	$ret['file'] = $fname;
      }
    }

    $ret['cash_item_date'] = $data['cash_item_date'];
    if(empty($ret['cash_item_date'])) 		{ $this->db->rollback(); return array('failure'=>true, 'msg'=> 'Заполните дату'); }

    $ret['cash_item_nmcl_cb'] = $this->add_refbook($data["cash_item_nmcl_cb"], "cashes_nom");
    if( $ret['cash_item_nmcl_cb'] == 0) 	{ $this->db->rollback(); return array('failure'=>true, 'msg'=> 'Ошибка добавления товара'); }

    $ret['cash_item_prod_type_cb'] = $this->add_refbook($data["cash_item_prod_type_cb"], "cashes_group");
    if($ret['cash_item_prod_type_cb'] == 0) 	{ $this->db->rollback(); return array('failure'=>true, 'msg'=> 'Ошибка добавления группы товара'); }

    $ret['cash_item_price'] = $data['cash_item_price'];
    if(empty($ret['cash_item_price'])) 	{ $this->db->rollback(); return array('failure'=>true, 'msg'=> 'Заполните цену'); }

    $ret['cash_item_currency_cb'] = $this->add_refbook($data["cash_item_currency_cb"], "currency");
    if($ret['cash_item_currency_cb'] == 0) 	{ $this->db->rollback(); return array('failure'=>true, 'msg'=> 'Ошибка добавления валюты'); }

    $ret['cash_item_ctype_cb'] = $this->add_refbook($data["cash_item_ctype_cb"], "cashes_type");
    if($ret['cash_item_ctype_cb'] == 0) 	{ $this->db->rollback(); return array('failure'=>true, 'msg'=> 'Ошибка добавления кошелька'); }

    $ret['cash_item_qnt'] = $data['cash_item_qnt'];
    if(empty($ret['cash_item_qnt'])) 		{ $this->db->rollback(); return array('failure'=>true, 'msg'=> 'Заполните количество'); }

    $ret['cash_item_org_cb'] = $this->add_refbook($data["cash_item_org_cb"], "cashes_org");
    if($ret['cash_item_org_cb'] == 0) 		{ $this->db->rollback(); return array('failure'=>true, 'msg'=> 'Ошибка добавления организации'); }

    $ret['cash_item_toper_cb'] = intval($data['cash_item_toper_cb']);
    if( !in_array($ret['cash_item_toper_cb'], array(0,1)) )	{ $this->db->rollback(); return array('failure'=>true, 'msg'=> 'Неверный тип операции'); }

    $ret['cash_item_note'] = $data['cash_item_note'];

    return $ret;
  }

  public function getFile($id) {
    if(!$this->usr->canRead()) return "";
    return $this->db->element("SELECT `file` FROM `cashes` WHERE id = ? AND bd_id = ?", $id, $this->usr->db_id);
  }

  public function edit($data, $files) {
    if(!$this->usr->canWrite()) return "Ошибка доступа";

    $this->db->start_tran();

    $fl = $this->getFile($data['cash_item_edit_id']);
    if(!empty($fl)) {
      @unlink(__DIR__."/../".$fl);
    }

    $refb = $this->refbook_check($data, $files);

    if($refb['failure']) return $refb;

    //CURRENT_TIMESTAMP перенести на сторону БД
    $sql =
    "UPDATE `cashes`
      SET nmcl_id = ?,
	  `group` = ?,
	  price = ?,
	  cash_type_id = ?,
	  qnt = ?,
	  `date` = ?,
	  org_id = ?,
	  `file` = ?,
	  `type` = ?,
	  note = ?,
	  cur_id = ?,
	  date_edit = CURRENT_TIMESTAMP
     WHERE id = ? ";

    $this->db->exec($sql,
	$refb['cash_item_nmcl_cb'],
	$refb['cash_item_prod_type_cb'],
	$refb['cash_item_price'],
	$refb['cash_item_ctype_cb'],
	$refb['cash_item_qnt'],
	$refb['cash_item_date'],
	$refb['cash_item_org_cb'],
	$refb['file'],
	$refb['cash_item_toper_cb'],
	$refb['cash_item_note'],
	$refb['cash_item_currency_cb'],
	intval($data['cash_item_edit_id'])
    );

    $cnt = intval( $this->db->affect() );
    if($cnt == 0) { $this->db->rollback(); return array('failure'=>true, 'msg'=> 'Ошибка обновления операции'); }

    $this->db->commit();

    //1% to check db
    if(rand(1,100) == 50) $this->analize();

    return array('success'=>true, 'msg'=> $cnt );
  }

  public function add($data, $files) {
    if(!$this->usr->canWrite()) return "Ошибка доступа";

    $this->db->start_tran();

    $refb = $this->refbook_check($data, $files);

    if($refb['failure']) return $refb;

    $sql =
    "INSERT INTO `cashes` (nmcl_id, `group`, price, cash_type_id, qnt, `date`, org_id, bd_id, uid, `file`, `type` ,note, cur_id)
     VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $this->db->exec($sql,
	$refb['cash_item_nmcl_cb'],
	$refb['cash_item_prod_type_cb'],
	$refb['cash_item_price'],
	$refb['cash_item_ctype_cb'],
	$refb['cash_item_qnt'],
	$refb['cash_item_date'],
	$refb['cash_item_org_cb'],
	$this->usr->db_id,
	$this->usr->id,
	$refb['file'],
	$refb['cash_item_toper_cb'],
	$refb['cash_item_note'],
	$refb['cash_item_currency_cb']
    );

    $id = intval( $this->db->last_id() );
    if($id == 0) { $this->db->rollback(); return array('failure'=>true, 'msg'=> 'Ошибка добавления операции'); }

    $this->db->commit();

    return array('success'=>true, 'msg'=> $id );
  } //add

  public function analize() {
    if(!$this->usr->canWrite()) return "Ошибка доступа";

    $this->db->exec("analyze cashes;");
    $this->db->exec("analyze cashes_group;");
    $this->db->exec("analyze cashes_nom;");
    $this->db->exec("analyze cashes_org;");
    $this->db->exec("analyze cashes_type;");
    $this->db->exec("analyze currency;");
    $this->db->exec("analyze db;");
    $this->db->exec("analyze users;");
    $this->db->exec("vacuum;");

    return true;
  }
}
?>
