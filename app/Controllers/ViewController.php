<?php

namespace Kugel\Controllers;

use Kugel\Models\AvisoMDFe;
use Kugel\Models\NFe;
use Kugel\Models\Problema;

use Kugel\Models\ContingenciaAtivada;
use Kugel\Models\ContingenciaAgendada;
use Kugel\Models\HeaderMail;
use Kugel\Models\Informe;
use Kugel\Models\DocumentoDiverso;
use Kugel\Models\DocumentoTecnico;
use Kugel\Models\ESocial;
use Kugel\Models\Vaga;

use Kugel\Utils\EmailUtils;
use Kugel\Utils\ESocialUtils;
use Kugel\Utils\SefazUtils;
use Kugel\Utils\VagasUtils;

class ViewController extends Controller {
    /*
    * Página inicial
    */
    public function viewIndex($request, $response) {
        $problemas = Problema::orderBy('updated_at', 'desc')->paginate(5)->appends($request->getParams());
        return $this->view->render($response, 'index.twig', compact('problemas'));
    }

    /*
    * Página sobre
    */
    public function viewSobre($request, $response) {
        return $this->view->render($response, 'sobre.twig', compact('problemas'));
    }

    /*
    * Página novo item
    */
    public function viewNovo($request, $response) {
        return $this->view->render($response, 'novo.twig', compact('problemas'));
    }

    /*
    * Página de visualização do item
    */
    public function viewProblema($request, $response) {
        $problema = Problema::find($request->getAttribute('id'));
        if (!$problema) {
            $this->flash->addMessage('error', 'Item não encontrado! Tente a pesquisa!');
            return $response->withRedirect($this->router->pathFor('index'));
        }
        return $this->view->render($response, 'problema.twig', compact('problema'));
    }

    /*
    * Página de alteração do item
    */
    public function viewAlterar($request, $response) {
        $id = $request->getAttribute('id');
        $p = Problema::find($id);
        if (!$p) {
            $this->flash->addMessage('error', 'Item não encontrado para alteração!');
            return $response->withRedirect($this->router->pathFor('index'));
        }
        if (strpos($p->situacao, "<br>") !== FALSE) {
            $p->situacao = str_replace("<br>", "\r\n", $p->situacao);
        }
        if (strpos($p->solucao, "<br>") !== FALSE) {
            $p->solucao = str_replace("<br>", "\r\n", $p->solucao);
        }
        return $this->view->render($response, 'alterar.twig', compact('p'));
    }

    /*
    * Página de exclusão do item
    */
    public function viewExcluir($request, $response) {
        $id = $request->getAttribute('id');
        $p = Problema::find($id);
        if (!$p) {
            $this->flash->addMessage('error', 'Item não encontrado para exclusão!');
            return $response->withRedirect($this->router->pathFor('index'));
        }
        return $this->view->render($response, 'excluir.twig', compact('p'));
    }

    /*
    * Página de consulta NFe na receita
    */
    public function viewConsultaNFe($request, $response) {
        $dataSite = SefazUtils::getConsultaNFe();

        $data = [
            'contAtivList' => [],
            'contAgendList' => [],
            'informeList' => [],
            'docDiversosList' => [],
            'docNotaTecList' => []
        ];

        // ContingenciaAtivada
        foreach ($dataSite['contAtivList'] as $item) {
            $result = ContingenciaAtivada::where('texto', $item)->first();
            if (!$result) {
                $c = ContingenciaAtivada::create([
                    'texto' => $item,
                    'visto' => 'S',
                ]);
                array_push($data['contAtivList'], $c);
            } else {
                if ($result->visto == 'N') {
                    array_push($data['contAtivList'], $result);
                }
            }
        }

        // ContingenciaAgendada
        foreach ($dataSite['contAgendList'] as $item) {
            $result = ContingenciaAgendada::where('texto', $item)->first();
            if (!$result) {
                $c = ContingenciaAgendada::create([
                    'texto' => $item,
                    'visto' => 'S',
                ]);
                array_push($data['contAgendList'], $c);
            } else {
                if ($result->visto == 'N') {
                    array_push($data['contAgendList'], $result);
                }
            }
        }

        // Informe
        foreach ($dataSite['informeList'] as $item) {
            $result = Informe::where('texto', $item)->first();
            if (!$result) {
                $c = Informe::create([
                    'texto' => $item['texto'],
                    'visto' => 'S',
                    'endereco' => $item['endereco'],
                ]);
                array_push($data['informeList'], $c);
            } else {
                if ($result->visto == 'N') {
                    array_push($data['informeList'], $result);
                }
            }
        }

        // DocumentoDiverso
        foreach ($dataSite['docDiversosList'] as $item) {
            $result = DocumentoDiverso::where('texto', $item)->first();
            if (!$result) {
                $c = DocumentoDiverso::create([
                    'texto' => $item,
                    'visto' => 'S',
                ]);
                array_push($data['docDiversosList'], $c);
            } else {
                if ($result->visto == 'N') {
                    array_push($data['docDiversosList'], $result);
                }
            }
        }

        // DocumentoTecnico;
        foreach ($dataSite['docNotaTecList'] as $item) {
            $result = DocumentoTecnico::where('texto', $item)->first();
            if (!$result) {
                $c = DocumentoTecnico::create([
                    'texto' => $item,
                    'visto' => 'S',
                ]);
                array_push($data['docNotaTecList'], $c);
            } else {
                if ($result->visto == 'N') {
                    array_push($data['docNotaTecList'], $result);
                }
            }
        }

        /* Envia o e-mail com os novos registros */
        $enviarEmail =
            count($data['contAtivList']) > 0 ||
            count($data['contAgendList']) > 0 ||
            count($data['informeList']) > 0 ||
            count($data['docDiversosList']) > 0 ||
            count($data['docNotaTecList']) > 0;

        if ($enviarEmail) {
            // Enviar e-mail
            
            $mensagem = '<html><body><p style="font-family: Helvetica, Arial, sans-serif; font-size: 18px; line-height: 12px; color: rgb(33, 33, 33); margin-bottom: 10px;">Olá!</p>'.
                '<br><p style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 12px; color: rgb(33, 33, 33); margin-bottom: 10px;">Existem novos dados no portal da Sefaz NFe!</p>'.
                '<br>';

            if (count($data['contAtivList']) > 0) {
                $mensagem .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 12px; color: rgb(33, 33, 33); margin-bottom: 10px;">';
                $mensagem .= 'Serviço(s) em contingência ativada:';
                $mensagem .= '<ul>';

                foreach ($data['contAtivList'] as $item) {
                    $mensagem .= '<li>' . $item->texto . '</li>';
                }
                $mensagem .= '</ul></p>';
            }

            if (count($data['contAgendList']) > 0) {
                $mensagem .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 12px; color: rgb(33, 33, 33); margin-bottom: 10px;">';
                $mensagem .= 'Serviço(s) em contingência agendada:';
                $mensagem .= '<ul>';

                foreach ($data['contAgendList'] as $item) {
                    $mensagem .= '<li>' . $item->texto . '</li>';
                }
                $mensagem .= '</ul></p>';
            }

            if (count($data['informeList']) > 0) {
                $mensagem .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 12px; color: rgb(33, 33, 33); margin-bottom: 10px;">';
                $mensagem .= 'Informes:';
                $mensagem .= '<ul>';

                foreach ($data['informeList'] as $item) {
                    $mensagem .= '<li><a target="_blank" href="' . $item->endereco . '">' . $item->texto . '</a></li>';
                }
                $mensagem .= '</ul></p>';
            }

            if (count($data['docDiversosList']) > 0) {
                $mensagem .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 12px; color: rgb(33, 33, 33); margin-bottom: 10px;">';
                $mensagem .= 'Documentos :: Diversos:';
                $mensagem .= '<ul>';

                foreach ($data['docDiversosList'] as $item) {
                    $mensagem .= '<li>' . $item->texto . '</li>';
                }
                $mensagem .= '</ul></p>';
            }

            if (count($data['docNotaTecList']) > 0) {
                $mensagem .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 12px; color: rgb(33, 33, 33); margin-bottom: 10px;">';
                $mensagem .= 'Documentos :: Notas Técnicas:';
                $mensagem .= '<ul>';

                foreach ($data['docNotaTecList'] as $item) {
                    $mensagem .= '<li>' . $item->texto . '</li>';
                }
                $mensagem .= '</ul></p>';
            }

            $mensagem .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 12px; line-height: 20px; color: rgb(33, 33, 33); margin-bottom: 10px;">Atenciosamente,<br></p>';
            $mensagem .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 10px; line-height: 12px; margin-bottom: 10px;">';
            $mensagem .= '<span style="font-weight: bold; color: rgb(33, 33, 33); display: inline;" class="txt signature_companyname-target sig-hide">Bot Kugel Info!</span>';
            $mensagem .= '<span class="company-sep break" style="display: inline;"></span>';
            $mensagem .= '</p></body></html>';

            $mailer = EmailUtils::enviarEmail(
                ['ricardo@kugel.com.br',
                 'sigi@kugel.com.br',
                 'valdecir@kugel.com.br',
                 'gustavo@kugel.com.br'
                ],
                'Novos dados no portal da Sefaz NFe!',
                $mensagem
            );
    
            if ($mailer) {
                return "OK";
            } else {
                return "FAIL: " . $mailer->ErrorInfo;
            }
        } else {
            return "OK";
        }
    }

    public function viewNoticiasESocialSE($request, $response) {
        $dataSite = ESocialUtils::getNoticias();
        $data = [];
        $mostrar = $request->getAttribute('mostrar');

        if ($mostrar == '') {
            $mostrar = 'naovistos';
        }

        foreach ($dataSite as $item) {
            $result = ESocial::where('url', $item['url'])->first();
            if (!$result) {
                if (strlen($item['title']) > 100) {
                    $item['title'] = substr($item['title'], 0, 100);
                }

                $c = ESocial::create([
                    'titulo'       => $item['title'],
                    'url'          => $item['url'],
                    'texto_url'    => $item['url_text'],
                    'descricao'    => $item['description'],
                    'publicado_em' => $item['when'],
                    'publicado_as' => $item['at'],
                    'visto'        => 'N',
                ]);
                array_push($data, $c);
            } else {
                if ($mostrar == 'vistos' && $result->visto == 'S') {
                    array_push($data, $result);
                } else if ($mostrar == 'naovistos' && $result->visto == 'N') {
                    array_push($data, $result);
                }
            }
        }

        return $this->view->render($response, 'consultaesocial.twig', compact('data'));
    }

    public function viewConsultaJessicaSE($request, $response) {
        $dataSite = VagasUtils::getVagas();

        /*
        if (TRUE) {
            return $response->withJson($dataSite);
        }
        */

        $data = [];
        $mostrar = $request->getAttribute('mostrar');

        if ($mostrar == '') {
            $mostrar = 'naovistos';
        }

        foreach ($dataSite as $item) {
            $result = Vaga::where('urlVaga', $item['urlVaga'])->first();
            if (!$result) {
                try {
                    $v = Vaga::create([
                        'nomeVaga'       => $item['nomeVaga'],
                        'nomeEmpresa'    => $item['nomeEmpresa'],
                        'tipoVaga'       => $item['tipoVaga'],
                        'miniTextoVaga'  => $item['miniTextoVaga'],
                        'dataPublicacao' => $item['dataPublicacao'],
                        'urlVaga'        => $item['urlVaga'],
                        'visto'          => 'N',
                    ]);
                    array_push($data, $v);
                }
                catch (\Illuminate\Database\QueryException $e) {
                    $v = Vaga::create([
                        'nomeVaga'       => $item['nomeVaga'],
                        'nomeEmpresa'    => $item['nomeEmpresa'],
                        'tipoVaga'       => $item['tipoVaga'],
                        'miniTextoVaga'  => utf8_encode($item['miniTextoVaga']),
                        'dataPublicacao' => $item['dataPublicacao'],
                        'urlVaga'        => $item['urlVaga'],
                        'visto'          => 'N',
                    ]);
                    array_push($data, $v);
                }
            }
            else {
                if ($mostrar == 'vistos' && $result->visto == 'S') {
                    array_push($data, $result);
                }
                else if ($mostrar == 'naovistos' && $result->visto == 'N') {
                    array_push($data, $result);
                }
            }
        }

        return $this->view->render($response, 'consultajessica.twig', compact('data'));
    }

    public function viewConsultaJessica($request, $response) {
        return "OK";
    }

    public function viewTest($request, $response) {
        // Enviar e-mail
        $mensagem = "<html>";
        $mensagem .= "<body>";
        $mensagem .= "<h1>Teste ok</h1>";
        $mensagem .= "</body>";
        $mensagem .= "</html>";

        $mailer = EmailUtils::enviarEmail(
            ['ricardo@kugel.com.br'],
            'E-mail de teste!',
            $mensagem
        );

        if ($mailer) {
            return $response->withJson("OK");
        } else {
            return $response->withJson("Oops: " . $mailer->ErrorInfo);
        }
    }

    public function viewNoticiasESocial($request, $response) {
        $dataSite = ESocialUtils::getNoticias();
        $data = [];
        $mostrar = $request->getAttribute('mostrar');

        if ($mostrar == '') {
            $mostrar = 'naovistos';
        }

        foreach ($dataSite as $item) {
            $result = ESocial::where('url', $item['url'])->first();
            if (!$result) {
                $c = ESocial::create([
                    'titulo'       => $item['title'],
                    'url'          => $item['url'],
                    'texto_url'    => $item['url_text'],
                    'descricao'    => $item['description'],
                    'publicado_em' => $item['when'],
                    'publicado_as' => $item['at'],
                    'visto'        => 'S',
                ]);
                array_push($data, $c);
            } else {
                if ($result->visto == 'N') {
                    array_push($data, $result);
                }
            }
        }

        /* Envia o e-mail com os novos registros */
        $enviarEmail = count($data) > 0;

        if ($enviarEmail) {
            $mensagem =
                '<html>'.
                '  <body>'.
                '   <p style="font-family: Helvetica, Arial, sans-serif; font-size: 18px; line-height: 12px; color: rgb(33, 33, 33); margin-bottom: 10px;">'.
                '     Olá!'.
                '   </p>'.
                '   <br>'.
                '   <p style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 12px; color: rgb(33, 33, 33); margin-bottom: 10px;">'.
                '     Novas notícias foram publicadas no portal do eSocial!'.
                '   </p>'.
                '   <br>';

                foreach ($data as $item) {
                    $mensagem .=
                '   <p style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; color: rgb(33, 33, 33); margin-bottom: 10px;">'.
                '     Título: ' . (empty($item->titulo)? 'Não informado' : $item->titulo) . '<br>'.
                '     URL: <a target="_blank" href="'.$item->url.'">'.$item->texto_url.'</a><br>';

                    if (!empty(trim($item->descricao))) {
                        $mensagem .= '     Descrição: ' . $item->descricao . '<br>';
                    }

                    $mensagem .=
                '     Publicado em: ' . $item->publicado_em . ' às ' . $item->publicado_as .
                '   </p>';
                }

            // Rodapé
            $mensagem .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 12px; line-height: 20px; color: rgb(33, 33, 33); margin-bottom: 10px;">Atenciosamente,<br></p>';
            $mensagem .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 10px; line-height: 12px; margin-bottom: 10px;">';
            $mensagem .= '<span style="font-weight: bold; color: rgb(33, 33, 33); display: inline;" class="txt signature_companyname-target sig-hide">Bot Kugel Info!</span>';
            $mensagem .= '<span class="company-sep break" style="display: inline;"></span>';
            $mensagem .= '</p></body></html>';

            $mailer = EmailUtils::enviarEmail(
                ['ricardo@kugel.com.br',
                 'sigi@kugel.com.br',
                 'gustavo@kugel.com.br'
                ],
                'Novas noticias no portal eSocial!',
                $mensagem
            );

            if ($mailer) {
                return "OK";
            } else {
                return "FAIL: " . $mailer->ErrorInfo;
            }
        } else {
            return "OK";
        }
    }

    public function viewConsultaNFeSE($request, $response) {
        $dataSite = SefazUtils::getConsultaNFe();
        $mostrar = $request->getAttribute('mostrar');

        if ($mostrar == '') {
            $mostrar = 'naovistos';
        }

        $data = [
            'contAtivList' => [],
            'contAgendList' => [],
            'informeList' => [],
            'docDiversosList' => [],
            'docNotaTecList' => []
        ];

        // ContingenciaAtivada
        foreach ($dataSite['contAtivList'] as $item) {
            $result = ContingenciaAtivada::where('texto', $item)->first();
            if (!$result) {
                $c = ContingenciaAtivada::create([
                    'texto' => $item,
                    'visto' => 'N',
                ]);
                array_push($data['contAtivList'], $c);
            } else {
                if ($mostrar == 'vistos' && $result->visto == 'S') {
                    array_push($data['contAtivList'], $result);
                } else if ($mostrar == 'naovistos' && $result->visto == 'N') {
                    array_push($data['contAtivList'], $result);
                }
            }
        }

        // ContingenciaAgendada
        foreach ($dataSite['contAgendList'] as $item) {
            $result = ContingenciaAgendada::where('texto', $item)->first();
            if (!$result) {
                $c = ContingenciaAgendada::create([
                    'texto' => $item,
                    'visto' => 'N',
                ]);
                array_push($data['contAgendList'], $c);
            } else {
                if ($mostrar == 'vistos' && $result->visto == 'S') {
                    array_push($data['contAgendList'], $result);
                } else if ($mostrar == 'naovistos' && $result->visto == 'N') {
                    array_push($data['contAgendList'], $result);
                }
            }
        }

        // Informe
        foreach ($dataSite['informeList'] as $item) {
            $result = Informe::where('texto', $item['texto'])->first();
            if (!$result) {
                $c = Informe::create([
                    'texto' => $item['texto'],
                    'visto' => 'N',
                    'endereco' => $item['endereco'],
                ]);
                array_push($data['informeList'], $c);
            } else {
                if ($mostrar == 'vistos' && $result->visto == 'S') {
                    array_push($data['informeList'], $result);
                } else if ($mostrar == 'naovistos' && $result->visto == 'N') {
                    array_push($data['informeList'], $result);
                }
            }
        }

        // DocumentoDiverso
        foreach ($dataSite['docDiversosList'] as $item) {
            $result = DocumentoDiverso::where('texto', $item)->first();
            if (!$result) {
                $c = DocumentoDiverso::create([
                    'texto' => $item,
                    'visto' => 'N',
                ]);
                array_push($data['docDiversosList'], $c);
            } else {
                if ($mostrar == 'vistos' && $result->visto == 'S') {
                    array_push($data['docDiversosList'], $result);
                } else if ($mostrar == 'naovistos' && $result->visto == 'N') {
                    array_push($data['docDiversosList'], $result);
                }
            }
        }

        // DocumentoTecnico;
        foreach ($dataSite['docNotaTecList'] as $item) {
            $result = DocumentoTecnico::where('texto', $item)->first();
            if (!$result) {
                $c = DocumentoTecnico::create([
                    'texto' => $item,
                    'visto' => 'N',
                ]);
                array_push($data['docNotaTecList'], $c);
            } else {
                if ($mostrar == 'vistos' && $result->visto == 'S') {
                    array_push($data['docNotaTecList'], $result);
                } else if ($mostrar == 'naovistos' && $result->visto == 'N') {
                    array_push($data['docNotaTecList'], $result);
                }
            }
        }

        return $this->view->render($response, 'consultanfe.twig', compact('data'));
    }

    public function viewConsultaMDFeSE($request, $response) {
        $dataSite = SefazUtils::getConsultaMDFe();
        $mostrar = $request->getAttribute('mostrar');

        if ($mostrar == '') {
            $mostrar = 'naovistos';
        }

        $data = [
            'avisosList' => [],
            'noticiasList' => [],
            'documentosList' => [],
        ];

        // AvisoMDFe
        foreach ($dataSite['avisosList'] as $item) {
            $result = AvisoMDFe::where('titulo', $item['titulo'])->first();
            if (!$result) {
                $c = AvisoMDFe::create([
                    'titulo' => $item['titulo'],
                    'descricao' => $item['descricao'],
                    'publicado_em' => $item['data'],
                    'visto' => 'N',
                ]);
                array_push($data['avisosList'], $c);
            } else {
                if ($mostrar == 'vistos' && $result->visto == 'S') {
                    array_push($data['avisosList'], $result);
                } else if ($mostrar == 'naovistos' && $result->visto == 'N') {
                    array_push($data['avisosList'], $result);
                }
            }
        }

        // NoticiaMDFe
        /*
        foreach ($dataSite['noticiasList'] as $item) {
            $result = NoticiaMDFe::where('texto', $item)->first();
            if (!$result) {
                $c = NoticiaMDFe::create([
                    'texto' => $item,
                    'visto' => 'N',
                ]);
                array_push($data['noticiasList'], $c);
            } else {
                if ($mostrar == 'vistos' && $result->visto == 'S') {
                    array_push($data['noticiasList'], $result);
                } else if ($mostrar == 'naovistos' && $result->visto == 'N') {
                    array_push($data['noticiasList'], $result);
                }
            }
        }

        // DocumentoMDFe
        foreach ($dataSite['documentosList'] as $item) {
            $result = DocumentoMDFe::where('texto', $item['texto'])->first();
            if (!$result) {
                $c = DocumentoMDFe::create([
                    'texto' => $item['texto'],
                    'visto' => 'N',
                    'endereco' => $item['endereco'],
                ]);
                array_push($data['documentosList'], $c);
            } else {
                if ($mostrar == 'vistos' && $result->visto == 'S') {
                    array_push($data['documentosList'], $result);
                } else if ($mostrar == 'naovistos' && $result->visto == 'N') {
                    array_push($data['documentosList'], $result);
                }
            }
        }*/

        return $this->view->render($response, 'consultamdfe.twig', compact('data'));
    }

    public function viewConsultaMDFe($request, $response) {
        $dataSite = SefazUtils::getConsultaMDFe();
        $mostrar = $request->getAttribute('mostrar');

        if ($mostrar == '') {
            $mostrar = 'naovistos';
        }

        $data = [
            'avisosList' => [],
            'noticiasList' => [],
            'documentosList' => [],
        ];

        // AvisoMDFe
        foreach ($dataSite['avisosList'] as $item) {
            $result = AvisoMDFe::where('titulo', $item['titulo'])->first();
            if (!$result) {
                $c = AvisoMDFe::create([
                    'titulo' => $item['titulo'],
                    'descricao' => $item['descricao'],
                    'publicado_em' => $item['data'],
                    'visto' => 'N',
                ]);
                array_push($data['avisosList'], $c);
            } else {
                if ($mostrar == 'vistos' && $result->visto == 'S') {
                    array_push($data['avisosList'], $result);
                } else if ($mostrar == 'naovistos' && $result->visto == 'N') {
                    array_push($data['avisosList'], $result);
                }
            }
        }

        $enviarEmail =
            count($data['avisosList']) > 0 ||
            count($data['noticiasList']) > 0 ||
            count($data['documentosList']) > 0;

            if ($enviarEmail) {
                // Enviar e-mail
                
                $mensagem = '<html><body><p style="font-family: Helvetica, Arial, sans-serif; font-size: 18px; line-height: 12px; color: rgb(33, 33, 33); margin-bottom: 10px;">Olá!</p>'.
                    '<br><p style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 12px; color: rgb(33, 33, 33); margin-bottom: 10px;">Existem novos dados no portal do MDFe!</p>'.
                    '<br>';
    
                if (count($data['avisosList']) > 0) {
                    $mensagem .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 12px; color: rgb(33, 33, 33); margin-bottom: 10px;">';
                    $mensagem .= 'Aviso(s):';
                    $mensagem .= '<ul>';
    
                    foreach ($data['avisosList'] as $item) {
                        $mensagem .= '<li>' . $item->titulo . '</li>';
                    }
                    $mensagem .= '</ul></p>';
                }
    
                $mensagem .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 12px; line-height: 20px; color: rgb(33, 33, 33); margin-bottom: 10px;">Atenciosamente,<br></p>';
                $mensagem .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 10px; line-height: 12px; margin-bottom: 10px;">';
                $mensagem .= '<span style="font-weight: bold; color: rgb(33, 33, 33); display: inline;" class="txt signature_companyname-target sig-hide">Bot Kugel Info!</span>';
                $mensagem .= '<span class="company-sep break" style="display: inline;"></span>';
                $mensagem .= '</p></body></html>';
    
                $mailer = EmailUtils::enviarEmail(
                    ['ricardo@kugel.com.br',
                     'sigi@kugel.com.br',
                     'valdecir@kugel.com.br',
                     'daniel@kugel.com.br'
                    ],
                    'Novos dados no portal da MDFe!',
                    $mensagem
                );
        
                if ($mailer) {
                    return "OK";
                } else {
                    return "FAIL: " . $mailer->ErrorInfo;
                }
            } else {
                return "OK";
            }
    }
}
