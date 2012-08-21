<?php

/**
 * Classe de conexão para retorno e geração de botão de pagamento.
 *
 * @version 0.1
 * @link https://github.com/bruno-barros/PagSeguro-Codeigniter
 * @author Bruno Barros <brunodanca/gmail.com>
 * 
 * # Como gerar botão:  
 *   $this->pagseguro->set_user(id|array);
 *   $this->pagseguro->set_products(array);
 *   $this->pagseguro->get_button($config);
 * 
 */
class Pagseguro {

    private $ps_email = '';
    private $timeout = 30; // Timeout em segundo
    private $ci = NULL;
    private $this_user = array(
        'id'       => false,
        'nome'     => false,
        'ddd'      => false, // só números
        'telefone' => false, // só números
        'email'    => false,
        'shippingType' => 3, //1=Encomenda normal (PAC), 2=SEDEX, 3=Tipo de frete não especificado.
        'cep' => false,      // só números
        'logradouro' => '',
        'numero' => '',
        'compl' => '',
        'bairro' => '',
        'cidade' => '',
        'uf' => '',
        'pais' => 'BRA'
        ); // dados do usuário
   
    private $this_cart = false; // lista dos produtos
    public  $config = array(
        'reference' => false, // ID de referência da compra no sistema
        'button' => 'https://p.simg.uol.com.br/out/pagseguro/i/botoes/pagamentos/164x37-pagar-assina.gif' // imagem do botão de compra
    );

    // -------------------------------------------------------------------------
    
    public function __construct() {        
        $this->ci = &get_instance();
        $this->ci->load->config('pagseguro');
        $this->token = $this->ci->config->item('pagseguro_token');
        $this->ps_email = $this->ci->config->item('pagseguro_email');
    }

    // -------------------------------------------------------------------------
    // MÉTODOS DE RETONO DA TRANSAÇÃO
    // -------------------------------------------------------------------------
    /**
     * Métdo que se comunica com o PagSeguro, recebe o POST e retorna dados
     * da loja validando a requisição do usuário.
     * O retorno pode ser: VERIFICADO, FALSO, ...
     * @return string
     */
    public function notificationPost() {
//        log_message('debug', 'notificationPost() do PagSeguro.');
        $postdata = 'Comando=validar&Token='.$this->token;
        foreach ($_POST as $key => $value) {
            $valued = $this->clearStr($value);
            $postdata .= "&$key=$valued";
        }
        return $this->verify($postdata);
    }
    
    // -------------------------------------------------------------------------
    
    /**
     * Limpa string para enviar ao PagSeguro
     * @param string $str
     * @return string
     */
    private function clearStr($str) {
        if (!get_magic_quotes_gpc()) {
            $str = addslashes($str);
        }
        return $str;
    }
    
    // -------------------------------------------------------------------------
    
    /**
     * Faz conexão com os servidores do PagSeguro e recebe a string de retorno
     * @param string $data
     * @return string
     */
    private function verify($data) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://pagseguro.uol.com.br/pagseguro-ws/checkout/NPI.jhtml");
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
//        curl_setopt($curl, CURLOPT_HTTPHEADER, Array("Content-Type: application/xml; charset=ISO-8859-1"))
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $result = trim(curl_exec($curl));
        curl_close($curl);
        return $result;
    }

    

    // -------------------------------------------------------------------------
    /**
     * Retorna array com o número do pedido (extrato) e o status. 
     * Fonte: PagSeguro.
     * Retorno:
     *  array
     *      'code' => 3
     *      'status' => 'Paga'
     *      'reference' => 107
     * @param string $code
     * @return array
     */
    public function get_notification($code = NULL){
        
        // se não for passado um código, usará o $_POST que o PS envia
        if($code === NULL){
            $code = $_POST['notificationCode'];
        }
        
        $url = "https://ws.pagseguro.uol.com.br/v2/transactions/notifications/";
        $url .= $code . "?email=" . $this->ps_email . "&token=" . $this->token;

        // faz conexão
        $transaction = $this->curl_connection($url);

        // algo deu errado na autenticação
        if($transaction == 'Unauthorized'){
            log_message('erro', 'Notificação PagSeguro com problemas.');
            return FALSE;
        }
        
        // converte para objeto
        $xml = simplexml_load_string($transaction);

        // retorna
        return array(
            'code' => (int)$xml->status,
            'status' => $this->ps_stats((int)$xml->status),
            'reference' => (int)$xml->reference
            );
        
    }
    
    // -------------------------------------------------------------------------
    /**
     * Nomenclatura de notificações do PagSeguro.
     * @param int $indice
     * @return string
     */
    public function ps_stats($indice = 0){
        
        $status = array(
            0 => 'desconhecido',
            1 => 'Aguardando pagamento',
            2 => 'Em análise',
            3 => 'Paga',
            4 => 'Disponível',
            5 => 'Em disputa',
            6 => 'Devolvida',
            7 => 'Cancelada'
        );
        
        return $status[$indice];
        
    }

    // -------------------------------------------------------------------------
    /**
     * Método do PagSeguro para conexão via cRUL.
     * @param type $url
     * @param string $method GET com padrão
     * @param array $data
     * @param type $timeout 30
     * @param type $charset ISO
     * @return array
     */
    private function curl_connection($url, $method = 'GET', Array $data = null, $timeout = 30, $charset = 'ISO-8859-1') {
		
        if (strtoupper($method) === 'POST') {
            $postFields    = ($data ? http_build_query($data, '', '&') : "");
            $contentLength = "Content-length: ".strlen($postFields);
            $methodOptions = Array(
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $postFields,
                    );			
        } else {
            $contentLength = null;
            $methodOptions = Array(
                    CURLOPT_HTTPGET => true
                    );				
        }

        $options = Array(
            CURLOPT_HTTPHEADER => Array(
                "Content-Type: application/x-www-form-urlencoded; charset=".$charset,
                $contentLength
            ),	
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            //CURLOPT_TIMEOUT => $timeout
        ); 
        $options = ($options + $methodOptions);

        $curl = curl_init();
        curl_setopt_array($curl, $options);			
        $resp  = curl_exec($curl);
        $info  = curl_getinfo($curl);// para debug
        $error = curl_errno($curl);
        $errorMessage = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            log_message('error', $errorMessage);
//                throw new Exception("CURL can't connect: $errorMessage");
                return false;
        } else {
                return $resp;
        }
    }


    // -------------------------------------------------------------------------
    // MÉTODOS PARA GERAÇÃO DO BOTÃO DE COBRANÇA
    // -------------------------------------------------------------------------
    /**
     * Estabelece quais configurações foram enviadas.
     * Obrigatórias:
     *  array(
     *      'reference' => int
     * )
     * @param type $config
     */
    private function button_config($config){
        
        
        if(! is_array($config)){
            $config = array($config);
        }
        
        foreach($this->config as $chv => $vlr){
            
            if(isset($config[$chv])){
                $this->$chv = $config[$chv];
            } else {
                $this->$chv = $vlr;
            }
            
        }
    }

    // -------------------------------------------------------------------------
    /**
     * Recebe as configurações e gera botão.
     * @param type $config
     * @return type
     */
    public function get_button($config = NULL){ 
        
        // primeira coisa, parsear as configurações
        $this->button_config($config);
        
        if($this->reference === FALSE && !is_numeric($this->reference)){
            return '<!-- Erro ao gerar botão -->';
        }
        
        $button = $this->get_form_open();
        
        // opcional
        if($this->this_user['nome']){ 
            $button .= $this->get_user_inputs();
        }
        
        if($this->get_products_inputs() === FALSE){
            return '<!-- Erro ao gerar botão -->';
        }
        
        $button .= $this->get_products_inputs();
        $button .= $this->get_form_close();
        
        return $button;
    }

    // -------------------------------------------------------------------------
    /**
     * Recebe e prapara dados do usuário... opcional
     * @param array $user_array
     * @return boolean|string
     */
    public function set_user($user_array){
        
        $this->ci = &get_instance();
        
        $user_array = $this->user_parser($user_array);        
        
        foreach($this->this_user as $chv => $vlr){
            
            if(isset($user_array[$chv])){
                $this->this_user[$chv] = $user_array[$chv];
            }            
        } 
       
    }
    
    // -------------------------------------------------------------------------
    
    /**
     * Retorna dados do usuário na memória.
     * @return type
     */
    public function get_user(){
        return $this->this_user;
    }


    // -------------------------------------------------------------------------
    /**
     * Prepara dados do usuário para o PagSeguro
     * @param type $user_array
     * @return boolean
     */
    private function user_parser($user_array){
        
        if(!is_array($user_array)){
            return FALSE;
        }       
        
        $return = array();
        
        foreach($user_array as $c => $v){

            // cep
            if($c == 'cep'){
                $v = str_replace(array(',', '.', ' '), '', $v);
            }
            
            // telefone
            if($c == 'tel1'){
                $return['ddd'] = substr($v, 0, 2);
                $return['telefone'] = substr(str_replace('-', '', $v), -8);
            }
            // tel2
            if($c == 'tel2' && strlen($return['ddd']) != 2){
                $return['ddd'] = substr($v, 0, 2);
                $return['telefone'] = substr(str_replace('-', '', $v), -8);
            }
            
            // número
            if($c == 'num'){
                $return['numero'] = $v;
            }
            
            $return[$c] = $v;
        }
        
        return $return;
        
    }


    // -------------------------------------------------------------------------
    /**
     * baseado nas configurações, monta o formulário
     * @param array $user_array
     * @return string
     */
    public function get_user_inputs(){
        $f = array();
        // '<!-- Dados do comprador (opcionais) -->  
        $f[] = '<input type="hidden" name="senderName" value="'.$this->this_user['nome'].'">';
        $f[] = '<input type="hidden" name="senderAreaCode" value="'.$this->this_user['ddd'].'">';
        $f[] = '<input type="hidden" name="senderPhone" value="'.$this->this_user['telefone'].'">';
        $f[] = '<input type="hidden" name="senderEmail" value="'.$this->this_user['email'].'">';
        
        // <!-- Informações de frete (opcionais) -->  
        $f[] = '<input type="hidden" name="shippingType" value="'.$this->this_user['shippingType'].'">'; 
        $f[] = '<input type="hidden" name="shippingAddressPostalCode" value="'.$this->this_user['cep'].'">';  
        $f[] = '<input type="hidden" name="shippingAddressStreet" value="'.$this->this_user['logradouro'].'">';  
        $f[] = '<input type="hidden" name="shippingAddressNumber" value="'.$this->this_user['numero'].'">';  
        $f[] = '<input type="hidden" name="shippingAddressComplement" value="'.$this->this_user['compl'].'">';  
        $f[] = '<input type="hidden" name="shippingAddressDistrict" value="'.$this->this_user['bairro'].'">';  
        $f[] = '<input type="hidden" name="shippingAddressCity" value="'.$this->this_user['cidade'].'">';  
        $f[] = '<input type="hidden" name="shippingAddressState" value="'.$this->this_user['uf'].'">';  
        $f[] = '<input type="hidden" name="shippingAddressCountry" value="'.$this->this_user['pais'].'">';
        
        return implode("\n", $f);
        
    }
    
    // -------------------------------------------------------------------------
    /**
     * Recebe o array com um produto, ou array multi com vários
     * Campos:
     *      id
     *      descricao
     *      valor
     *      quantidade
     *      peso
     * @param array $product_array
     */
    public function set_products($product_array){
        
        if(!is_array($product_array)){
            log_message('error', 'set_products() Não existem produtos para PagSeguro');
            return FALSE;
        }
        
        // verifica se é um único produto, ou multi array
        if(isset($product_array[0]) && is_array($product_array[0])){
            // já é multi array... vários produtos
            $this->this_cart = $product_array;
        } else {
            // um único produto
            $this->this_cart = array($product_array);
        }
        
    }
    
    // -------------------------------------------------------------------------
    
    /**
     * baseado nas configurações, monta o formulário
     */
    private function get_products_inputs(){
        
        if($this->this_cart === FALSE){
            return FALSE;
        }
        
        $ttl = count($this->this_cart);
        
        $f = array();
        //<!-- Itens do pagamento (ao menos um item é obrigatório) -->        
        // percorre os produtos
        for($x = 0; $x < $ttl; $x++){
            $id = $x+1;
            
            $itemId          = $this->this_cart[$x]['id'];
            $itemDescription = $this->this_cart[$x]['descricao'];
            $itemAmount      = $this->this_cart[$x]['valor'];            
            $itemQuantity    = $this->this_cart[$x]['quantidade'];
            $itemWeight      = $this->this_cart[$x]['peso'];
            
            
            $f[] = '<input type="hidden" name="itemId'.$id.'" value="'.$itemId.'">';
            $f[] = '<input type="hidden" name="itemDescription'.$id.'" value="'.$itemDescription.'">';  
            $f[] = '<input type="hidden" name="itemAmount'.$id.'" value="'.$itemAmount.'">';  
            $f[] = '<input type="hidden" name="itemQuantity'.$id.'" value="'.$itemQuantity.'">';  
            $f[] = '<input type="hidden" name="itemWeight'.$id.'" value="'.$itemWeight.'">';
            
        }      
          
        
        return implode("\n", $f);
    }


    // -------------------------------------------------------------------------
    /**
     * Gera a parte inicial do form
     * @return string
     */
    private function get_form_open(){
        $f = array();
        $f[] = '<form target="pagseguro" method="post" action="https://pagseguro.uol.com.br/v2/checkout/payment.html">';
        // '<!-- Campos obrigatórios -->';
        $f[] = '<input type="hidden" name="receiverEmail" value="'.$this->ps_email.'">';
        $f[] = '<input type="hidden" name="currency" value="BRL">';
        $f[] = '<input type="hidden" name="encoding" value="UTF-8">';
        //<!-- Código de referência do pagamento no sistema (opcional) -->  
        $f[] = '<input type="hidden" name="reference" value="'.$this->reference.'">';
        
        return implode("\n", $f);
    }
    
    // -------------------------------------------------------------------------
    /**
     * Gera a parte final do form
     * @return string
     */
    private function get_form_close(){
        $f = array();
        //<!-- submit do form (obrigatório) -->  
        $f[] = '<input type="image" class="btn-pagseuro" name="submit" src="'.$this->button.'" alt="Pague com PagSeguro">';
        $f[] = '</form>';
        return implode("\n", $f);
    }
    
    

}

?>