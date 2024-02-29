<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

require APPPATH . 'libraries/RestController.php';

// use chriskacerguis\RestServer\RestController;

class ApiController extends RestController
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('mapos_model');
    }

    public function index_get()
    {
        $result = new stdClass;
        $result->countOs   = $this->mapos_model->count('os');
        $result->clientes  = $this->mapos_model->count('clientes');
        $result->produtos  = $this->mapos_model->count('produtos');
        $result->servicos  = $this->mapos_model->count('servicos');
        $result->garantias = $this->mapos_model->count('garantias');
        $result->vendas    = $this->mapos_model->count('vendas');
        
        $result->osAbertas    = $this->mapos_model->getOsAbertas();
        $result->osAndamento  = $this->mapos_model->getOsAndamento();
        $result->estoqueBaixo = $this->mapos_model->getProdutosMinimo();

        $this->response([
            'status'  => true,
            'message' => 'Dashboard',
            'result'  => $result,
        ], RestController::HTTP_OK);
    }

    public function login_post()
    {
        $this->load->library('form_validation');
        $this->form_validation->set_rules('email', 'E-mail', 'valid_email|required|trim');
        $this->form_validation->set_rules('senha', 'Senha', 'required|trim');
        if ($this->form_validation->run() == false) {
            $this->response([
                'status' => false,
                'message' => validation_errors()
            ], RestController::HTTP_UNAUTHORIZED);
        }

        $this->load->model('Mapos_model');
        $email    = $this->input->post('email');
        $password = $this->input->post('senha');
        $user     = $this->Mapos_model->check_credentials($email);

        if ($user) {
            // Verificar se acesso está expirado
            if ($this->chk_date($user->dataExpiracao)) {
                $this->response([
                    'status' => false,
                    'message' => 'A conta do usuário está expirada, por favor entre em contato com o administrador do sistema.'
                ], RestController::HTTP_UNAUTHORIZED);
            }

            // Verificar credenciais do usuário
            if (password_verify($password, $user->senha)) {
                $this->log_app('Efetuou login no app', $user->nome);
                
                $this->load->model('Apikeys_model');
                $data = [
                    'user_id'      => $user->idUsuarios,
                    'ci_key'       => md5(time()),
                    'level'        => $user->permissoes_id,
                    'ip_addresses' => $this->input->ip_address(),
                    'date_created' => date('Y-m-d H:i:s')
                ];

                if($this->Apikeys_model->add($data)) {
                    $this->CI = &get_instance();
                    $this->CI->load->database();
                    $this->CI->db->select('*');
                    $this->CI->db->where('idPermissao', $user->permissoes_id);
                    $this->CI->db->limit(1);
                    $array = $this->CI->db->get('permissoes')->row_array();
                    $permissoes = unserialize($array['permissoes']);

                    $result = [
                        'ci_key'      => $data['ci_key'],
                        'permissions' => [$permissoes]
                    ];

                    $this->response([
                        'status' => true,
                        'message' => 'Login realizado com sucesso!',
                        'result' => $result,
                    ], RestController::HTTP_OK);
                }
            }

            $this->response([
                'status'  => false,
                'message' => 'Os dados de acesso estão incorretos!'
            ], RestController::HTTP_UNAUTHORIZED);
        }

        $this->response([
            'status'  => false,
            'message' => 'Usuário não encontrado, verifique se suas credenciais estão corretas!'
        ], RestController::HTTP_UNAUTHORIZED);
    }

    private function chk_date($data_banco)
    {
        $data_banco = new DateTime($data_banco);
        $data_hoje  = new DateTime("now");

        return $data_banco < $data_hoje;
    }

    public function emitente_get()
    {
        $this->CI = &get_instance();
        $this->CI->load->database();

        $result = new stdClass;
        $result->appName  = $this->CI->db->get_where('configuracoes', ['config' => 'app_name'])->row_object()->valor;
        $result->emitente = $this->mapos_model->getEmitente() ?: false;

        $this->response([
            'status' => true,
            'message' => 'Dados do Map-OS',
            'result' => $result,
        ], RestController::HTTP_OK);
    }
}
