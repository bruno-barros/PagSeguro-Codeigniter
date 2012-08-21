PagSeguro-Codeigniter
=====================

Biblioteca de integração com PagSeguro para Codeigniter.

<h2>Como utilizar</h2>
<p>Veja o controller/exemplo_ps</p>

<p>Dados do cliente</p>
<code>$this->pagseguro->set_user($usuario);</code>

<p>Dados dos produtos</p>
<code>$this->pagseguro->set_products($products);</code>

<p>Identificador do pedido</p>
<code>$config['reference'] = '999';</code>

<p>Gera botão de pagamento</p>
<code>echo $this->pagseguro->get_button($config);</code>

<p>Para mais opções de configuração veja em 'libraries/Pagseguro.php'</p>