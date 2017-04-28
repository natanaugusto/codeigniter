<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Class estrutural para os Controllers
 * @author Natan Augusto <natanaugusto@gmail.com>
 */
abstract class NC_Controller extends CI_Controller {
	/**
	 * Propriedade para armazenar um objeto Model
	 * @var object
	 */
	private $model;
	/**
	 * Titulo da página
	 * @var string
	 */
	protected $title;
	/**
	 * Separador de titulo
	 * @var string
	 */
	protected $titleSeparator = '&bull;';
	/**
	 * Caminho da view do layout
	 * @var string
	 */
	protected $layout = 'tpl/layout';
	/**
	 * Caminho para a pasta que contem as assets
	 * @var string
	 */
	protected $assets = 'assets';
	/**
	 * Array com os estilos a serem carregados
	 * @var array
	 */
	protected $styles = array(
		'css/style.css',
		);
	/**
	 * Filtros a serem utilizados pela interface
	 * Exemplo 1: array(
	 * 	0 => array(
	 * 		'type' => 'text',
	 * 		'value' => '666',
	 * 		'label' => 'Label de Zica',
	 * 	)
	 * )
	 * @var array
	 */
	protected $filters;
	/**
	 * Variável de controle dos filtros
	 * @var boolean
	 */
	protected $resetFilter = TRUE;
	/**
	 * Página atual em que o site se encontra
	 * @var string
	 */
	protected $currentPage;
	/**
	 * Array com os scripts a serem carregados
	 * @var array
	 */
	protected $scripts = array(
		'js/jquery-1.11.1.min.js',
		'js/script.js',
		);
	/**
	 * Token do WP
	 * Mal feito e lixão
	 * @var object
	 */
	private $wpToken;
	/**
	 * Guarda os valores passados pelos filtros
	 * @var array
	 */
	protected $filtersData;
	/**
	 * Usuário logado no sistema
	 * Usado para analise de acesso
	 * @var array
	 */
	protected $loggedUser;
	/**
	 * Método construtor
	 */
	public function __construct() {
		parent::__construct();
		$this->validateWpLogin();
		$this->load->helper(array(
			'url',
			'assets',
			'link',
			));
		$this->processCurrentURI();
		$this->processFilterData();
	}
	/**
	 * Adiciona mais CSSs a serem carregados na página
	 * @param mixed(string|array) $styles Estilos a serem carregados
	 */
	protected function addStyle($styles) {
		if(is_string($styles))
			$this->styles[] = $styles;
		elseif(is_array($styles))
			foreach($styles as $style)
				$this->styles[] = $style;
		}
	/**
	 * Adiciona mais JSs a serem carregados na página
	 * @param mixed(string|array) $scripts Scripts a serem carregados
	 */
	protected function addScript($scripts) {
		if(is_string($scripts))
			$this->scripts[] = $scripts;
		elseif(is_array($scripts))
			foreach($scripts as $script)
				$this->scripts[] = $script;
		}
	/**
	 * Renderiza uma view unindo-a com o layout
	 * @param  string $content View Nome da view a ser carregada
	 * @param  array $content Data Array com as variaveis a serem passadas para a view
	 * @return void Imprime a página na tela
	 */
	protected function render($contentView, $contentData = null) {
		$content = $this->load->view($contentView, $contentData, TRUE);
		$data = array(
			'logged_user'	=> 	$this->loggedUser,
			'title'			=>	$this->title,
			'assets'		=>	$this->assets,
			'styles'		=>	$this->styles,
			'scripts'		=>	$this->scripts,
			'current_page'	=>	$this->currentPage,
			'content'		=>	$content,
			);

		$this->load->view($this->layout, $data);
	}
	/**
	 * Processa as mascaras passadas pela propriedade1 self::columns
	 * @param  array $results Resultados gerados pela consulta
	 * @param 	array $columns Colunas com  as mascaras a serem adicionadas
	 * @return array Retorna os resultados da consulta com as mascaras processadas
	 */
	protected function processMasks($results, $columns) {
		if(!is_array($results)){
			return null;
                }
		foreach($results as &$row) {
			$row = (array)$row;
			array_walk_recursive($row, function(&$value, $key) use($columns, $row) {
				if(isset($columns[$key]['mask'])) {
					$eval = str_replace(':value:', $value, "\$value={$columns[$key]['mask']};");
					eval($eval);
                                }
			});
                        //var_dump($row['DataAceite']);
				
                        
		}
                
		return $results;
	}
	/**
	 * Processa os filtros gerando as condições para o select
	 * @param  array $filters Filtros a serem processados
	 * @param  object &$query  Objeto QueryBuilder relacionado a query.
	 * O objeto é passado com associação
	 */
	protected function processFilters($filters, &$query) {
		foreach($filters as $filter)
			if(!empty($filter['value']) && isset($filter['query']))
				$query->$filter['query']['operator'](
					$filter['query']['column'],
					preg_match("/\d{2}\/\d{2}\/\d{4}/", $filter['value']) ? mask_db_date($filter['value']) : $filter['value']);
		}
	/**
	 * Processa as querys para que sejam adicionadas validações
	 * por cargo e usuários.
	 * Todas os métodos que utilizarem dessa classe, devem ter a segurança de que
	 * os campos table.distribuidor_id e table.area_venda_id existam. Já que o controle
	 *  de Usuários é baseado nessas informações
	 *
	 * @param  object $query     Objeto QueryBuilder do CI
	 * @param  string $tableJoin Tabela onde os Joins de validação serão feitos
	 */
	protected function processAccess(&$query, $tableJoin) {
		//Valida por cargo
		if($this->loggedUser['cargo_id'] == 5) {
			$query = $query
			->join('wp_rel_usuario_distribuidor wru',
				"wru.distribuidor_id = {$tableJoin}.distribuidor_id and wru.usuario_id = {$this->loggedUser['usuario_id']}",
				'inner');
		}
		if(in_array($this->loggedUser['cargo_id'], array(3,4))) {
			$this->load->model('Model');
			$modelQuery = $this->Model->getQueryBuilder()
			->select('area_venda_id')
			->distinct()
			->where('usuario_id', $this->loggedUser['usuario_id'])
			->get('wp_rel_usuario_area')
			->result_array();
			if(count($modelQuery) > 0) {
				$areasId = array();
				foreach($modelQuery as $area)
					$areasId[] = $area['area_venda_id'];

				$query->where_in('area_id', $areasId);
			} else {
				$query->where(FALSE);
			}
		}
	}
	/**
	 * Processa a URI para ficar apenas o controller e o methodo acessados
	 */
	private function processCurrentURI() {
		$this->currentPage = $this->router->fetch_class() . '/' . $this->router->fetch_method();
	}
	/**
	 * Função mal feita para validar o login do WP
	 * Essa função deverá ser excomungada o mais rápido possível
	 */
	private function validateWpLogin() {
		//POG lixão pra caber no XGH
		session_start();
		$this->wpToken = isset($_SESSION['token']) ? $_SESSION['token']->getDados() : null;
		if(!is_array($this->wpToken))
			redirect(site_url());
		$this->load->model('Model');
		$usuario = $this->Model->db
		->select('cargo_id')
		->where('usuario_id', $this->wpToken['usuario_id'])
		->get('wp_rel_usuario_cargo')
		->result_array();

		$this->loggedUser = array(
			'usuario_id' => $this->wpToken['usuario_id'],
			'cargo_id' => $usuario[0]['cargo_id'],
			);

		if(!in_array($usuario[0]['cargo_id'], array(3,4,5,6)))
			redirect(site_url());
	}
	/**
	 * Processa os dados dos filtros baseados em POST e SESSION
	 * Não foi possível utilizar a library Session do CI por causa
	 * Das Sessions do modafoca Wp
	 */
	protected function processFilterData() {
		if((!isset($_SESSION['current_page']) || is_null($_SESSION['current_page'])
			|| $_SESSION['current_page'] != $this->currentPage) && $this->resetFilter === TRUE)
			unset($_SESSION['filters_data']);

		if(count($this->input->post()) > 1) {
			$post = $this->input->post();

			$_SESSION['current_page'] = $this->currentPage;
			$_SESSION['filters_data'] = $post;

			redirect('http://' . $this->input->server('HTTP_HOST') . '/' . base_url() . index_page() . '/' . $this->currentPage);
		}
		$this->filtersData = isset($_SESSION['filters_data']) ? $_SESSION['filters_data'] : null;
	}
	/**
	 * Retorna o valor do filtro solicitado
	 * @param  string $name Nome do filtro
	 * @return array|null
	 */
	protected function getFilterData($name = null) {
		if(!is_null($this->filtersData) && is_array($this->filtersData)) {
			if(!is_string($name))
				return $this->filtersData;

			if(is_string($name) &&  isset($this->filtersData[$name]))
				return $this->filtersData[$name];
		}

		return null;
	}
}
