<?php

class Exemplo_ps extends CI_Controller{
    
    public function __construct() {
        parent::__construct();
        
        $this->load->library('pagseguro');
    }
    
    /**
     * Exemplo de como gerar botão de pagamento.
     */
    public function index(){

        // OPCIONAL //
        // dados do usuário para gerar botão
        $usuario = array(
            'id'         => 1,
            'nome'       => 'Fulano da Silva',
            'ddd'        => '21', // só números
            'telefone'   => '99887766', // só números
            'email'      => 'emaildo@cliente.com',
            'shippingType' => 3, //1=Encomenda normal (PAC), 2=SEDEX, 3=Tipo de frete não especificado.
            'cep'        => '24210445',      // só números
            'logradouro' => 'Rua do Cliente',
            'numero'     => '123',
            'compl'      => '456',
            'bairro'     => 'Meu bairro',
            'cidade'     => 'Minha cidade',
            'uf'         => 'RJ',
            'pais'       => 'BRA'
        );
        $this->pagseguro->set_user($usuario);
        
        
        // insere produtos para botão PagSeguro
        $products[] = array(
            'id' => '999',
            'descricao' => 'Este é um produto de teste',
            'valor' => '1.56',
            'quantidade' => 1,
            'peso' => 0
        );
        $products[] = array(
            'id' => '777',
            'descricao' => 'Este é outro produto',
            'valor' => '6.70',
            'quantidade' => 2,
            'peso' => 0
        );
        $this->pagseguro->set_products($products);
        
        // ID do pedido
        $config['reference'] = rand(999, 9999);

        // gera botão
        echo $this->pagseguro->get_button($config);
    }
    
    // -------------------------------------------------------------------------
    /**
     * Método de retorno do pag seguro
     * Conteúdo do POST:
     * VendedorEmail: email@pagseguro.com.br
     * TransacaoID: 23A080959E0346F58B8C73D2F032E814 <= 
     * Referencia: 169 <= ID de cms_extrato
     * Extras: 0,00
     * TipoFrete: FR <=
     * ValorFrete: 0,00 <=
     * Anotacao: <=
     * DataTransacao: 31/07/2012 01:03:59 <=
     * TipoPagamento: Pagamento Online <=
     * StatusTransacao: Aguardando Pagto|Aprovado <=
     * CliNome: Nome do usurio
     * CliEmail: emaildo@cliente.com
     * CliEndereco: rua alguma coisa
     * CliNumero: 0
     * CliComplemento:
     * CliBairro: ing
     * CliCidade: Niteri
     * CliEstado: RJ
     * CliCEP: 24210445
     * CliTelefone: 21 33335555
     * NumItens: 2
     * Parcelas: 1 <=
     * ProdID_1: 129
     * ProdDescricao_1: Descrio obrigatria
     * ProdValor_1: 0,90
     * ProdQuantidade_1: 1
     * ProdFrete_1: 0,00
     * ProdExtras_1: 0,00
     * ProdID_2: 112
     * ProdDescricao_2: 2 Descrio obrigatria
     * ProdValor_2: 0,10
     * ProdQuantidade_2: 1
     * ProdFrete_2: 0,00
     * ProdExtras_2: 0,00
     */
    public function retorno_pagseguro() {

        if (count($_POST) > 0) {
            
            // SALVA O POST PARA DEGUG
            $this->debug($_P0ST);

            $informacao = array();

            // POST recebido, indica que é a requisição do NPI,
            // ou notificação de status
            $this->load->library('pagseguro'); //Carrega a library
            
            
            // faz conexão com PS para validar o retorno
            $retorno = $this->pagseguro->notificationPost();

            // quando recebe uma notificação que necessita uma requisição GET 
            // para recuperar status da transação
            $notificationType = (isset($_POST['notificationType']) && $_POST['notificationType'] != '') ? $_POST['notificationType'] : FALSE;
            $notificationCode = (isset($_POST['notificationCode']) && $_POST['notificationCode'] != '') ? $_POST['notificationCode'] : FALSE;

            // É uma notificação de status. Passa a ação para o método que vai 
            // atualizar o status do pedido.
            if ($notificationType && $notificationCode) {
                
                $not = $this->pagseguro->get_notification($notificationCode);
                /*
                 * FAZ AS ATUALIZAÇÕES COM A NOTIFICAÇÃO DE STATUS
                 */          
                
            }

            // informação quando é enviado um POST completo
            $transacaoID = (isset($_POST['TransacaoID'])) ? $_POST['TransacaoID'] : FALSE;

            // Se existe $transacaoID é uma notificação via POST logo após a
            // solicitação de pagamento, neste momento
            if ($transacaoID) {
                
                /*
                 * FAZ AS ATUALIZAÇÕES COM A NOTIFICAÇÃO DE STATUS
                 */
                
            }

            
            
            //O post foi validado pelo PagSeguro.
            if ($retorno == "VERIFICADO") {              

                if ($_POST['StatusTransacao'] == 'Aprovado') {
                    $informacao['status'] = '1';
                } elseif ($_POST['StatusTransacao'] == 'Em Análise') {
                    $informacao['status'] = '2';
                } elseif ($_POST['StatusTransacao'] == 'Aguardando Pagto') {
                    $informacao['status'] = '3';
                } elseif ($_POST['StatusTransacao'] == 'Completo') {
                    $informacao['status'] = '4';
                } elseif ($_POST['StatusTransacao'] == 'Cancelado') {
                    $informacao['status'] = '5';
                }
            } else if ($retorno == "FALSO") {
                //O post não foi validado pelo PagSeguro.
                $informacao['status'] = '1000';
            } else {
                //Erro na integração com o PagSeguro.
                $informacao['status'] = '6';
            }
        } else {
            // POST não recebido, indica que a requisição é o retorno do Checkout PagSeguro.
            // No término do checkout o usuário é redirecionado para este bloco.
            // redirecionar para página de OBRIGADO e aguarde...
            // redirect('loja');
        }
        
        
    }
    
    // -------------------------------------------------------------------------
    
    /**
     * Exemplode como consultar status de notificação
     * @param string $code
     */
    public function check($code = NULL) {        

        if($code === NULL){
            $code = '45AC39-82659E659E9A-72242E0FAAB7-1EEBBF';
        }

        $string = $this->pagseguro->get_notification($code);

        var_dump($string);
    }
    
    // -------------------------------------------------------------------------
    /**
     * Salva um array no arquivo pagseguro...php em cache/
     * @param type $array
     */
    public function debug($array) {

        $data = array();
        foreach ($array as $c => $v) {
            $data[] = $c . ': ' . $v;
        }

        $output = implode("\n", $data);

        $this->load->helper('file');
        write_file(APPPATH . "cache/pagseguro_" . date("Y-m-d_h-i") . ".php", $output);
    }
    
}