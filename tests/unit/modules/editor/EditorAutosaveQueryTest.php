<?php

class EditorAutosaveQueryTest extends \Codeception\TestCase\Test
{
    private $files = array();
    private $previousDB;
    private $previousDBInfo;
    public function _before()
    {
        $this->previousDB = isset($GLOBALS['__DB__']) ? $GLOBALS['__DB__'] : array();
        $this->previousDBInfo = Context::getDBInfo();
    }
    public function _after()
    {
        foreach($this->files as $file) if(is_file($file)) unlink($file);
        $GLOBALS['__DB__'] = $this->previousDB;
        Context::setDBInfo($this->previousDBInfo);
    }
	private function compileQuery($name, $args)
	{
		Context::setDBInfo((object)array('master_db' => array('db_type' => 'compatibility')));
		$GLOBALS['__DB__']['compatibility'] = new AutosaveQueryDB();
		$file = tempnam(sys_get_temp_dir(), 'xe-autosave-query-');
		$this->files[] = $file;
		$parser = new XmlQueryParser();
		$parser->parse_xml_query('editor.' . $name, _XE_PATH_ . 'modules/editor/queries/' . $name . '.xml', $file);
		return include $file;
	}

	public function testGuestZeroRemainsAnOwnershipConditionInAutosaveQueries()
	{
		foreach(array('getSavedDocument', 'deleteSavedDoc', 'updateSavedDocTarget') as $name)
		{
			$args = (object)array('module_srl' => 100, 'member_srl' => 0, 'certify_key' => str_repeat('a', 40), 'document_srl' => 200);
			$query = $this->compileQuery($name, $args);
			$this->assertTrue($query instanceof Query);
			$where = $query->getWhereString();
			$this->assertTrue((bool)preg_match('/member_srl`?\s*=\s*[\x27\x22]?0/', $where), $where);
			$this->assertTrue(strpos($where, 'certify_key') !== false, $where);
			$this->assertTrue(strpos($where, 'module_srl') !== false, $where);
		}
	}

	public function testTargetUpdatesCannotCompileWithoutModuleAndAuthorConditions()
	{
		foreach(array('module_srl', 'member_srl') as $missing)
		{
			$args = (object)array('module_srl' => 100, 'member_srl' => 41, 'document_srl' => 200);
			unset($args->$missing);
			$query = $this->compileQuery('updateSavedDocTarget', $args);
			$this->assertTrue($query instanceof BaseObject && !$query->toBool(), 'An incomplete ownership condition must return a query validation error');
		}
	}
}

class AutosaveQueryDB extends DB
{
    public $prefix = 'xe_';
    public function __construct() {}
    public function getParser($force = false) { return new DBParser('`', '`', 'xe_'); }
	public function addQuotes($value) { return "'" . str_replace("'", "''", $value) . "'"; }
}
