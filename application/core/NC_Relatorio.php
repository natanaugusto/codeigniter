<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Controller para gerar as páginas de relatórios
 * @author Natan Augusto <natanaugusto@gmail.com>
 */
abstract class VL_Relatorio extends VL_Controller {
	/**
	 * Atributos das tags para a geração do Excel
	 * @var array
	 */
	protected $tagsAttr = array(
		'th' => array(
			'bgcolor' => '#f9f6f1'
			),
		);
	/**
	 * Construtor
	 */
	public function __construct() {
		parent::__construct();
		$this->load->helper(array(
			'masks',
			'filters',
			));
		$this->addStyle(array('css/relatorios.css'));
	}

	/**
	 * Processa e printa a view responsavel pelo relatório
	 * @param  integer $page    Página atual
	 * @param  array $filters Filtros a serem iniciados
	 * @param string $view Caminho da view a ser carregada
	 * @param  string $model   Nome do model responsavel pela grid
	 * @return view
	 */
	protected function showGrid($page, $filters, $view = 'relatorios/grid', $model = null) {
		$model = is_null($model) ? $this->model : $model;
		if(is_null($model))
			return FALSE;

		if($this->getFilterData('filtrar') != 1) {
			$this->render('relatorios/nofilter', array('filters' => $filters));
			return;
		}

		$this->load->model($model);
		$query = $this->$model->getQueryBuilder();
		$this->processAccess($query, $this->$model->table);
		$this->processFilters($filters, $query);
		if($this->input->get('excel') == 1) {
			$results = $this->$model->getAllResults();
			$data = array(
				'results'	=>	$this->processMasks($results, $this->$model->getColumns()),
				'labels'	=>	$this->$model->getLabels());

			if($this->showExcel($data, $model))
				return;
		}

		$results = $this->$model->getResults($page);
		$data = array(
			'model'			=>	$model,
			'logged_user'	=>	$this->loggedUser,
			'results'		=>	$this->processMasks($results, $this->$model->getColumns(TRUE)),
			'labels'		=>	$this->$model->getLabels(),
			'total'			=>	$this->$model->getTotal(),
			'page_count'	=>	$this->$model->getPageCount(),
			'page'			=>	$page,
			'assets'		=> 	$this->assets,
			'filters'		=>	$filters,
			'current_page'	=>	$this->currentPage,
			);

		$this->render($view, $data);
	}
	/**
	 * Recupera os distribuidores do banco de dados
	 * @return array
	 */
	protected function getDistribuidor() {
		$this->load->model('Model');
		$query = $this->Model->getQueryBuilder();
		$query = $query
		->select(array('d.id','d.nome'))
		->where('teste','N')
		->order_by('nome','ASC')
		->from('wp_distribuidor d');

		if($this->loggedUser['cargo_id'] == 5) {
			$query = $query
			->join('wp_rel_usuario_distribuidor wru',
				"wru.distribuidor_id = d.id and wru.usuario_id = {$this->loggedUser['usuario_id']}",
				'inner');
		}

		$query = $query
		->get()
		->result_array();

		$results = array();
		foreach($query as $value)
			$results[$value['id']] = $value['nome'];

		return $results;
	}
	/**
	 * Recupera os seguimentos do banco de dados
	 * @return array
	 */
	protected function getSegmentos() {
		$this->load->model('Model');
		$query = $this->Model->getQueryBuilder();
		$query = $query
		->select(array('segmento'))
		->where('segmento !=', 'teste')
		->where('segmento !=', '')
		->distinct()
		->get('wp_segmento')
		->result_array();

		$results = array();
		foreach($query as $value)
			$results[$value['segmento']] = $value['segmento'];

		return $results;
	}
	/**
	 * Gera o Excel para ser baixado
	 * @param  array $data Array com os resultados da
	 * consulta a serem printados no Excel
	 * @param  string $name Nome do arquivo a ser gerado(será adicionado data e hora)
	 * @return bool
	 */
	protected function showExcel($data, $name) {
		$this->load->library('ExcelGenerate');
		$cellDate = '<table><tr><td>' . gmdate('d/m/Y H:i:s') . '</table></tr></td>';
		ExcelGenerate::GenerateFromHtml(
			$cellDate . $this->load->view('relatorios/excel', $data, TRUE),
			$this->tagsAttr,
			$name.gmdate('YmdHis'));

		return TRUE;
	}
	/**
	 * Processa as variaveis passadas via $_GET
	 * @param string $model Model a ser utilizado como parametro
	 * @return array Retorna as colunas a serem usadas para gerar os Wheres
	 */
	protected function processGetVars($model) {
		$vars = $this->input->get();
		if(isset($vars['page']) && is_numeric($vars['page'])) {
			$this->page = (int)$vars['page'];
			unset($vars['page']);
		} else {
			$this->page = 1;
		}
		if(isset($vars['limit']) && is_numeric($vars['limit'])) {
			$this->limit = (int)$vars['limit'];
			unset($vars['limit']);
		} else {
			$this->limit = (int)$this->$model->get('limit');
		}
		if(isset($vars['columns']) && is_array($vars['columns'])) {
			$this->columns = $vars['columns'];
			unset($vars['columns']);
		} else {
			$this->columns = $this->$model->get('columns');
		}
		if(isset($vars['check-access']) && in_array($vars['check-access'], array('true','false','1','0'))) {
			$this->checkAccess = ($vars['check-access'] == 'true' || $vars['check-access'] == 1
				? true : false);
			unset($vars['check-access']);
		} else {
			$this->checkAccess = true;
		}
		array_walk($vars, function (&$value, $key) {
			$value = str_replace('~', '%', $value);
		});
		return $vars;
	}
}