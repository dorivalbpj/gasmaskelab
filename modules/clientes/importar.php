<?php
// modules/clientes/importar.php
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

// Array com todos os clientes
$clientes = [
    [
        'nome' => 'Mineiro Tattoo',
        'email' => null,
        'telefone' => '27996324277',
        'cpf_cnpj' => '37893825000192',
        'endereco' => 'Alça da Terceira Ponte, 245, 1º Andar, Sala 113, Centro Empresarial Praia da Costa Offices, Praia da Costa, Vila Velha - ES, CEP 29101-440.',
        'user_insta' => 'mineiro.tattoo',
        'posts_semanais' => 4,
        'videos_semana' => 2,
        'estaticos_semana' => 2,
        'captacao_mensal' => 1,
        'trafego_pago' => 1,
        'observacoes' => 'Contato: David Aguiar | Valor: R$ 1.100,00 | Vencimento: 15',
        'criado_em' => '2026-04-15'
    ],
    [
        'nome' => 'Full Gaz',
        'email' => null,
        'telefone' => '5191077206',
        'cpf_cnpj' => '45480385000170',
        'endereco' => 'Avenida Hugo Musso, 1555, Apartamento 1304, Itapuã, Vila Velha - ES, CEP 29101-785.',
        'user_insta' => 'fullgaz.consultoria',
        'posts_semanais' => 4,
        'videos_semana' => 2,
        'estaticos_semana' => 2,
        'captacao_mensal' => 0,
        'trafego_pago' => 0,
        'observacoes' => 'Contato: Laila Borba Gazul | Valor: R$ 3.100,00 | Vencimento: - | Observação: ja foi pago o valor total',
        'criado_em' => '2026-04-10'
    ],
    [
        'nome' => 'Gustavinn',
        'email' => null,
        'telefone' => '27999932909',
        'cpf_cnpj' => '32943196000144',
        'endereco' => 'Rua Ana Merotto Stefanon, 30, Sala 142, Cobilândia, Vila Velha - ES, CEP 29111-630.',
        'user_insta' => 'gustavinn_gv',
        'posts_semanais' => 10,
        'videos_semana' => 5,
        'estaticos_semana' => 5,
        'captacao_mensal' => 0,
        'trafego_pago' => 1,
        'observacoes' => 'Contato: Gustavo Venturini | Valor: R$ 1.800,00 | Vencimento: 15',
        'criado_em' => '2026-05-15'
    ],
    [
        'nome' => 'Renan Mariano · Advocacia Criminal',
        'email' => null,
        'telefone' => '11918606349',
        'cpf_cnpj' => null,
        'endereco' => null,
        'user_insta' => 'renanmariano.adv',
        'posts_semanais' => 4,
        'videos_semana' => 2,
        'estaticos_semana' => 2,
        'captacao_mensal' => 0,
        'trafego_pago' => 0,
        'observacoes' => 'Contato: Renan Mariano | Valor: R$ 0,00',
        'criado_em' => '2026-06-01'
    ],
    [
        'nome' => 'Indústria Ice Cream',
        'email' => 'contato@icecream-ind.com.br',
        'telefone' => '27981380077',
        'cpf_cnpj' => '282058000175',
        'endereco' => 'Rua Três, Lote 15, Pólo Empresarial Novo México, Vila Velha - ES, CEP 29104-374.',
        'user_insta' => 'ice.cream.vv/',
        'posts_semanais' => 3,
        'videos_semana' => 1,
        'estaticos_semana' => 2,
        'captacao_mensal' => 1,
        'trafego_pago' => 1,
        'observacoes' => 'Contato: Vladmir | Valor: R$ 1.300,00 | Vencimento: 10',
        'criado_em' => '2026-06-10'
    ],
    [
        'nome' => 'Nina Baby Store',
        'email' => null,
        'telefone' => '21971570582',
        'cpf_cnpj' => null,
        'endereco' => null,
        'user_insta' => 'ninababystore',
        'posts_semanais' => 2,
        'videos_semana' => 2,
        'estaticos_semana' => 0,
        'captacao_mensal' => 1,
        'trafego_pago' => 0,
        'observacoes' => 'Contato: Aline | Valor: R$ 0,00',
        'criado_em' => '2026-03-10'
    ],
    [
        'nome' => 'Hedge Consultioria',
        'email' => null,
        'telefone' => '27981398888',
        'cpf_cnpj' => '35369886000120',
        'endereco' => 'Rua Inácio Higino, 673, Sala 204, Praia da Costa, Vila Velha - ES, CEP 29101-087.',
        'user_insta' => 'hedgeconsultoria',
        'posts_semanais' => 5,
        'videos_semana' => 2,
        'estaticos_semana' => 3,
        'captacao_mensal' => 4,
        'trafego_pago' => 0,
        'observacoes' => 'Contato: Daniele | Valor: R$ 2.400,00 | Vencimento: 5',
        'criado_em' => '2026-01-01'
    ],
    [
        'nome' => 'LTV',
        'email' => null,
        'telefone' => '27981660123',
        'cpf_cnpj' => '40167164000122',
        'endereco' => 'Avenida Henrique Moscoso, 90, Salas 714 e 1301, Praia da Costa, Vila Velha - ES, CEP 29101-330.',
        'user_insta' => 'ltvcargo',
        'posts_semanais' => 5,
        'videos_semana' => 2,
        'estaticos_semana' => 3,
        'captacao_mensal' => 0,
        'trafego_pago' => 0,
        'observacoes' => 'Contato: Carlos | Valor: R$ 1.600,00 | Vencimento: 5',
        'criado_em' => '2026-01-01'
    ],
    [
        'nome' => 'Comexblog',
        'email' => null,
        'telefone' => '27981660123',
        'cpf_cnpj' => '10354346000144',
        'endereco' => null,
        'user_insta' => 'Comexblog',
        'posts_semanais' => 5,
        'videos_semana' => 2,
        'estaticos_semana' => 3,
        'captacao_mensal' => 0,
        'trafego_pago' => 0,
        'observacoes' => 'Contato: Carlos | Valor: R$ 1.600,00 | Vencimento: 5',
        'criado_em' => '2026-01-01'
    ],
    [
        'nome' => 'Hip',
        'email' => null,
        'telefone' => '279999460299',
        'cpf_cnpj' => '30189791000100',
        'endereco' => 'Rua João Antônio Afonso, 111, Condomínio Village Santa Inês, Casa 35, Santa Inês, Vila Velha - ES, CEP 29108-048.',
        'user_insta' => 'hipvaa',
        'posts_semanais' => 5,
        'videos_semana' => 2,
        'estaticos_semana' => 3,
        'captacao_mensal' => 1,
        'trafego_pago' => 0,
        'observacoes' => 'Contato: Patrick | Valor: R$ 500,00 | Vencimento: 15',
        'criado_em' => '2026-01-01'
    ],
    [
        'nome' => 'Animales',
        'email' => null,
        'telefone' => '27996099477',
        'cpf_cnpj' => '54777758000160',
        'endereco' => 'Rua Sete de Setembro, 13, Sala 104, Centro, Vila Velha - ES, CEP 29100-301.',
        'user_insta' => 'animalesvetclin24h',
        'posts_semanais' => 3,
        'videos_semana' => 1,
        'estaticos_semana' => 2,
        'captacao_mensal' => 0,
        'trafego_pago' => 0,
        'observacoes' => 'Contato: Ludimilla | Valor: R$ 500,00 | Vencimento: 15',
        'criado_em' => '2026-01-01'
    ],
    [
        'nome' => 'Juliana Dentista',
        'email' => null,
        'telefone' => '11940135511',
        'cpf_cnpj' => null,
        'endereco' => null,
        'user_insta' => 'drajulianasilvaaraujo',
        'posts_semanais' => 2,
        'videos_semana' => 2,
        'estaticos_semana' => 0,
        'captacao_mensal' => 0,
        'trafego_pago' => 1,
        'observacoes' => 'Contato: Juliana | Valor: R$ 700,00 | Vencimento: 6',
        'criado_em' => null
    ],
    [
        'nome' => 'Suricato',
        'email' => 'suricato.contas@gmail.com',
        'telefone' => '27981641919',
        'cpf_cnpj' => '14642528741',
        'endereco' => 'Rua Romero Lofego Botelho, 433, Loja 05, Praia da Costa, Vila Velha - ES, CEP 29101-063.',
        'user_insta' => 'barbearia_suricato',
        'posts_semanais' => 4,
        'videos_semana' => 2,
        'estaticos_semana' => 2,
        'captacao_mensal' => 1,
        'trafego_pago' => 1,
        'observacoes' => 'Contato: MARCELO DA SILVA PEREIRA | Valor: R$ 1.200,00 | Vencimento: 10',
        'criado_em' => null
    ],
    [
        'nome' => 'Lucas Badico',
        'email' => null,
        'telefone' => '11913713061',
        'cpf_cnpj' => '37504591000144',
        'endereco' => null,
        'user_insta' => null,
        'posts_semanais' => 1,
        'videos_semana' => 1,
        'estaticos_semana' => 0,
        'captacao_mensal' => 0,
        'trafego_pago' => 0,
        'observacoes' => 'Contato: Lucas Badico | Valor: R$ 600,00 | Vencimento: 5 | Observação: Vídeos semana: 0, Estáticos semana: 1',
        'criado_em' => null
    ]
];

$total = 0;
$erros = 0;

foreach ($clientes as $dados) {
    try {
        $sql = "INSERT INTO clientes (
            nome, email, telefone, cpf_cnpj, endereco, 
            user_insta, user_fb, user_tt, user_li, user_yt,
            posts_semanais, videos_semana, estaticos_semana, 
            roteiros, captacao_mensal, trafego_pago, 
            observacoes, criado_em
        ) VALUES (
            :nome, :email, :telefone, :cpf_cnpj, :endereco,
            :user_insta, :user_fb, :user_tt, :user_li, :user_yt,
            :posts_semanais, :videos_semana, :estaticos_semana,
            :roteiros, :captacao_mensal, :trafego_pago,
            :observacoes, :criado_em
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nome' => $dados['nome'],
            ':email' => $dados['email'],
            ':telefone' => $dados['telefone'],
            ':cpf_cnpj' => $dados['cpf_cnpj'],
            ':endereco' => $dados['endereco'],
            ':user_insta' => $dados['user_insta'],
            ':user_fb' => null,
            ':user_tt' => null,
            ':user_li' => null,
            ':user_yt' => null,
            ':posts_semanais' => $dados['posts_semanais'],
            ':videos_semana' => $dados['videos_semana'],
            ':estaticos_semana' => $dados['estaticos_semana'],
            ':roteiros' => null,
            ':captacao_mensal' => $dados['captacao_mensal'],
            ':trafego_pago' => $dados['trafego_pago'],
            ':observacoes' => $dados['observacoes'],
            ':criado_em' => $dados['criado_em']
        ]);
        $total++;
    } catch (PDOException $e) {
        $erros++;
        echo "Erro ao inserir {$dados['nome']}: " . $e->getMessage() . "<br>";
    }
}

echo "<div style='padding: 20px;'>";
echo "<h2>Importação concluída!</h2>";
echo "<p>✅ $total clientes importados com sucesso.</p>";
if ($erros > 0) {
    echo "<p>❌ $erros erros encontrados.</p>";
}
echo "<br><a href='index.php' class='btn btn-primary'>Voltar para lista</a>";
echo "</div>";