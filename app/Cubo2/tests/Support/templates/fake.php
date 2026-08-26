<?php
/**
 * Template de fixture. Exercita o contrato que os 279 getParam() dos templates
 * reais dependem: a $view global, o escape() novo e as variaveis locais que o
 * extract() dos params cria.
 *
 * @var \Cubo\View\View $view
 */
echo 'nome=' . $view->getParam('nome');
echo '|escapado=' . $view->escape('perigoso');
echo '|cru=' . $view->getParam('perigoso');
echo '|extract=' . $nome;
echo '|filho=' . $view->getParam('vindo_do_filho', 'ausente');





