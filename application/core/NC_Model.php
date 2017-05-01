<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Classe base para os models que serão utilizados nos
 * relatórios do sistema
 * @author Natan Augusto <natanaugusto@gmail.com>
 */
class NC_Model extends CI_Model {
	/**
	 * Tabela ou view que deverá interagir com o Model
	 * @var string
	 */
	protected $table;
	/**
	 * Colunas a serem trazidas do banco de dados
	 * Exemplo 1: array(
	 *  'status',
	 *  'date',
	 * )
	 * Exemplo 2: array(
	 *  'Status',
	 *  'date',
	 *  'data_cadastro' => 'Data de Cadastro'
	 * )
	 * Exemplo 3: array(
	 * 	'campo' => array(
	 * 	  'mask' => 'command(:value:)',
	 * 	  'label' => 'O campo',
	 * 	)
	 * )
	 * @var array
	 */
	protected $columns;
	/**
	 * Limite de itens por página
	 * @var integer
	 */
	protected $limit;
	/**
	 * Total de dados resultantes de uma query
	 * @var integer
	 */
	protected $count;
	/**
	 * Retorna um objeto QueryBuilder do CI
	 * @return object
	 */
	public function getQueryBuilder() {
		$this->db->reset_query();
		return $this->db;
	}
	/**
	 * Recupera os resultados de uma determinada consulta
	 * @param  $paramname descriptioninteger $page   Página corrente
	 * @param  integer $limit  Limite de itens por página
	 * @param  array $columns @see NC_Model::$columns
	 * @param  array $wheres Colunas e valores para ser definido os Wheres
	 * @return array Resultados da consulta
	 */
	public function getResults($page = 1, $limit = null, $columns = null, $wheres = null) {
		$limit = is_null($limit) ? $this->limit : $limit;
		if(is_array($columns))
			$this->setColumns($columns);

		$query  = $this->getQueryBuilder();

		if(is_array($wheres))
			$this->processWheres($query, $wheres);

		$this->count = $query->count_all_results($this->table, FALSE);

		return $query
		->select($this->getColumnsNames())
		->get(null, $limit, ($limit * --$page))
		->result_array();
	}
	/**
	 * Função para pegar um registro unico baseado no ID do registro
	 * @param mixed $ID Id do registro
	 * @param mixed $columns Colunas a serem trazidas @see NC_Model::getColumnsNames
	 * @param mixed $columnID Coluna que representa a PK
	 * @return array Array com os campos do registro
	 */
	public function getById($ID, $columns = null, $columnID = 'ID') {
		$return = $this->getByColumn($columnID, $ID, 1, $columns);
		return count($return) == 1 ? $return[0] : $return;
	}
	/**
	 * Retorna um ou mais registros dependendo da consulta feita
	 * @param string $column Nome da coluna a ser comparada
	 * @param string $value Valor a ser comparado (Deve ser passado com o prefixo % para entrar como like)
	 * @param integer $limit Limite de registros a serem trazidos
	 * @param array $columns Colunas a serem trazidas. Se não passado, carrega as colunas declaradas no Model
	 * @return array Registros retornados da consulta
	 */
	public function getByColumn($column, $value, $limit = null, $columns = null) {
		if($value  == '' || $value == '%')
			return null;

		$columns = is_null($columns) ? $this->getColumnsNames() : $columns;
		$limit = is_null($limit) ? $this->limit : $limit;
		$return = $this->db
		->select($columns);

		$this->processWheres($return, array($column=>$value));

		$return = $return
		->limit($limit)
		->get($this->table)
		->result_array();

		return $return;
	}
	/**
	 * Processa os Wheres a serem efetuados na Query
	 * @param object &$query QueryBuilder
	 * @param array $columns Colunas a serem validadas (array(coluna=>valor))
	 * @return void
	 */
	public function processWheres(&$query, $columns) {
		foreach($columns as $column=>$value) {
			if(!empty($value)) {
				switch ($column) {
					case 'in':
					$column = key($value);
					$query->where_in($column, $value[$column]);
					break;
					default:
					if(($side = strpos($value, '%')) !== FALSE) {
						$lastPos = strlen($value) - 1;
						switch ($side) {
							case 0:
							$side = $value[$lastPos] == '%' ? 'both' : 'before';
							break;
							case $lastPos:
							$side = 'after';
							break;
							default:
							$side = 'none';
							break;
						}
						$query->like($column, str_replace('%', '', $value), $side);
					} else {
						$query->where($column, $value);
					}
					break;
				}
			}
		}
	}
	/**
	 * Recupera os resultados da consulta sem utilizar limit
	 * @return array Resultados da consulta
	 */
	public function getAllResults() {
		$query = $this->db
		->select($this->getColumnsNames())
		->get($this->table);

		return $query->result_array();
	}
	/**
	 * Recupera as labels que devem ser colocadas na thead
	 * @return array
	 */
	public function getLabels() {
		$return = array();
		$i = 0;
		foreach($this->columns as $key => $column) {
			$index = is_string($key) ? $key : (is_string($column) ? $column : $i);
			if(preg_match('/^_[a-zA-Z0-9]+/', $index))
				continue;
			$return[$index] = isset($column['label']) ? $column['label'] :
			(is_string($key) && is_array($column) ? $key : $column);
			$i++;
		}

		return $return;
	}
	/**
	 * Recupera a quantia de páginas resultantes da consulta
	 * @return integer
	 */
	public function getPageCount() {
		$count = $this->getTotal();
		return ceil($count / $this->limit) > 0 ? ceil($count / $this->limit) : 1;
	}
	/**
	 * Recupera o total de registros gerados pela consulta
	 * @return integer
	 */
	public function getTotal() {
		return is_null($this->count) ? $this->db->count_all($this->table) : $this->count;
	}
	/**
	 * Retorna o array definido no atributo columns
	 * @param boolean $noAliasPrefix Usado para retirar os alias das colunas
	 * @return array
	 */
	public function getColumns($noAliasPrefix = FALSE) {
		$return= is_array($this->columns) ? $this->columns : null;
		if(!is_null($return) && $noAliasPrefix) {
			foreach ($return as $key => $value) {
				$newKey = preg_replace('/[a-zA-Z]*\./', '', $key);
				$newKey = str_replace('!', '', $newKey);
				$return[!$newKey ? $key : $newKey] = $return[$key];
				if($newKey != $key)
					unset($return[$key]);
			}
		}

		return $return;
	}
	/**
	 * Seta o array de columns
	 * @param array $columns Array com a formatação para columns
	 * @see  self::$columns
	 */
	public function setColumns($columns) {
		$this->columns = $columns;
	}
	/**
	 * Retorna um array contendo os nomes das colunas que serão trazidas do banco de dados
	 * @return array
	 */
	public function getColumnsNames() {
		$result = array();
		foreach($this->columns as $key=>$value) {
			$column = is_string($key) ? $key : $value;
			if(preg_match('/^_[a-zA-Z0-9]+/', $column))
				$result[] =  substr($column, 1) . ' AS ' . $column;
			elseif(preg_match('/^![a-zA-Z0-9]+/', $column))
				$result[] =  'null AS ' . substr($column, 1);
			else
				$result[] = $column;
		}

		return $result;
	}
	/**
	 * Recupera uma propriedade do objeto
	 * (Sim! Poderia ser melhor)
	 * @param string $property Propriedade a ser recuperada
	 * @return mixed
	 */
	public function get($property) {
		if(isset($this->$property))
			return $this->$property;
		return null;
	}
	/**
	 * Seta uma propriedade do objeto
	 * (Sim! Poderia ser melhor)
	 * @param string $property Propriedade a ser setada
	 * @param string $value Valor a ser setado na propriedade
	 * @return viod
	 */
	public function set($property, $value) {
		$this->$property = $value;
	}
}
