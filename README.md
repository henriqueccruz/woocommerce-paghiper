# WooCommerce - Módulo de boleto PagHiper 

Permite a emissão de boletos e integração do gateway da Paghiper ao seu WooCommerce.
Este módulo implementa emissão de boletos com retorno automático.

* **Versão mais Recente:** 2.0.4
* **Requer WooCommerce** versão mínima 3.5
* **Requer Wordpress** preferencialmente atualizado
* **Requisitos:** PHP >= 5.6.0, Suporte a JSON e permissões de escrita na pasta uploads.
* **Compatibilidade:** Wordpress 5.5.x, Woocommerce 3.5.x, PHP 7.4. Integrado diretamente ao Wordpress usando WC_API.


# Como Instalar

1. Crie sua conta na PagHiper [clicando aqui](https://www.paghiper.com/abra-sua-conta/);

2. Faça login e guarde suas **Chave de API (ApiKey)** e **Token** em Minha Conta > Credenciais;

3. No painel do seu site Wordpress, acesse a seção de plug-ins e clique em **Adicionar novo**. Digite "PagHiper" e aperte Enter;

4. Dentro da área administrativa do seu Wordpress, vá em: Woocommerce > Configurações > Finalizar Compra. Haver um item escrito "Boleto Bancário", com o ID paghiper. Clique neste item;

5. Ative o Boleto PagHiper marcando a primeira opção e preencha o restante do formulário com seu e-mail de cadastro da PagHiper e seu Token;

6. Configure a quantidade de dias que deseja dar de prazo no vencimento e comece a receber!

**Boas vendas!**

Se tiver dúvidas sobre esse processo, acesse nosso [guia de configuração de plugin](https://github.com/paghiper/woocommerce-paghiper/wiki/Configurando-o-plugin-no-seu-WHMCS)


# Suporte

Para questões relacionadas a integração e plugin, acesse o [forum de suporte no Github](https://github.com/paghiper/woocommerce-paghiper/issues);
Para dúvidas comerciais e/ou sobre o funcionamento do serviço, visite a nossa [central de atendimento](https://www.paghiper.com/atendimento/).

# Changelog

## Planejado para a próxima versão

* Envio de e-mails de lembrete automatizados pelo Woocommerce, com comunicação da loja para maior conversão
* Implementação de funcionalidade de boleto parcelado

## 2.0.4 - 2020/10/05

* Melhoria: Uso opcional do plug-in Brazilian Market on WooCommerce (antigo WooCommerce Extra Checkout Fields for Brazil)
* Melhoria: UX e acessibilidade na página de finalização de pedido
* Melhoria: Downgrade da versão do GuzzleHttp (evita conflitos com outros plug-ins que também usam a lib)
* Bugfix: Estabilidade e tratamento de erro na emissão e baixa de pagamentos
* Bugfix: Problema com Mimetype e permissões de acesso no gerador de código de barras
* Bugfix: Cálculo de desconto retornava valor incompleto em alguns casos
* Bugfix: Conciliação de estoque (no cancelamento de pedidos)
* Bugfix: Corrige alguns potenciais problemas relacionados a criação de log (dependendo da versão do Woocommerce)

## 2.0.3 - 2020/09/16

* Bigfix: Erro "payer_name invalido" ao finalizar pedido

## 2.0.2 - 2020/09/16

* Validação de ApiKey e avisos no back-end do Wordpress

## 2.0.1 - 2020/09/15

* Lógica de emissão de boleto re-escrita totalmente do zero
* Automação de pedido (boleto emitido automaticamente ao criar o pedido)
* Boleto anexo nos e-mails de notificação da loja
* Código de barras e linha digitável disponíveis na tela de confirmação do pedido e nos e-mails de notificação da loja 
* Melhor lógica de cálculo da data de vencimento na emissão dos boletos
* Melhor segurança ao salvar nova data de vencimento (validação e máscara de preenchimento)
* API 2.0 e novas funcionalidades
* Novos filtros disponíveis para maior personalização
* Implementação da API HTTP do Wordpress, para melhoria de performance e padronização
* Uso do novo PHP SDK
* Lançamento no repositório oficial do WP, permitindo instalação direto pelo painel
* FIX: Plugin agora suporta uso em lojas sem mod_rewrite disponível (links no formato https://loja/index.php/...)

# Licença

Copyright 2016 Serviços Online BR.

Licensed under the 3-Clause BSD License (the "License"); you may not use this file except in compliance with the License. You may obtain a copy of the License at

[https://opensource.org/licenses/BSD-3-Clause](https://opensource.org/licenses/BSD-3-Clause)

Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.
