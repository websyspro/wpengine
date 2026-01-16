<?php

namespace Websyspro\WpEngine\Shareds;

class WordPressCrawler
{
    protected string $userAgent = 'Websyspro WPEngine Installer/1.0';

    public function crawl(string $url, string $name = '/'): DirectoryNode
    {
        $html = $this->request($url);

        $dir = new DirectoryNode($name, $url);

        // captura apenas links <a href="...">
        preg_match_all(
            '#<a\s+href="([^"]+)">#i',
            $html,
            $matches
        );

        foreach ($matches[1] as $href) {

            // ignora voltar diretório
            if ($href === '../') {
                continue;
            }

            // ignora links absolutos (http, https, mailto, etc)
            if (preg_match('#^(https?:|mailto:)#i', $href)) {
                continue;
            }

            // ignora âncoras e vazios
            if ($href === '' || $href[0] === '#') {
                continue;
            }

            $fullUrl = rtrim($url, '/') . '/' . $href;

            // diretório
            if (str_ends_with($href, '/')) {
                $childName = rtrim($href, '/');

                $dir->addChild(
                    $this->crawl($fullUrl, $childName)
                );
            }
            // arquivo
            else {
                $dir->addChild(
                    new FileNode($href, $fullUrl)
                );
            }
        }

        return $dir;
    }

    protected function request(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => "User-Agent: {$this->userAgent}\r\n",
                'timeout' => 30,
            ]
        ]);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new \RuntimeException("Falha ao acessar: {$url}");
        }

        return $content;
    }
}
