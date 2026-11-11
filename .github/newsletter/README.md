# Arquivo da newsletter IA no Fone

Esta documentação fica em `.github/`, diretório já excluído do deploy pelo
`EXCLUDE` de `.github/workflows/deploy.yml`. Não colocar documentação ou uma
página de modelo dentro da pasta pública `ia/newsletter/`.

## Adicionar uma edição

1. Criar `ia/newsletter/AAAA-MM-DD/index.html` usando a data real da edição.
   Usar a estrutura visual e as fontes do arquivo `ia/newsletter/index.html`,
   com `lang="pt-BR"`, título próprio, landmarks e conteúdo acessível.
2. Inserir no `<head>` da edição:
   ```html
   <meta name="robots" content="noindex,nofollow">
   ```
   Não inserir canonical nem metadados que promovam a URL.
3. Colocar a versão web da edição, com assunto, data e conteúdo final.
   Remover marcadores do Sender, como unsubscribe, links de descadastro
   personalizados, dados de destinatários e rastreamento do e-mail.
4. No arquivo `ia/newsletter/index.html`, substituir o parágrafo
   `id="estado-vazio"` por uma lista estática na seção “Edições publicadas”.
   Cada item deve conter data, assunto e link para a edição. Por exemplo,
   substituindo os placeholders pelos valores reais antes de publicar:
   ```html
   <ul>
     <li>
       <time datetime="AAAA-MM-DD">DD/MM/AAAA</time> —
       <a href="/ia/newsletter/AAAA-MM-DD/">Assunto da edição</a>
     </li>
   </ul>
   ```
   Nas próximas edições, acrescentar um `<li>` no início da lista (mais recente
   primeiro). Se todas forem removidas, restaurar o parágrafo
   `<p id="estado-vazio">Ainda não há edições publicadas.</p>`.

## Discrição e verificação

- Não divulgar a URL nem adicionar links para o arquivo em outras páginas
  do site, inclusive home, `/ia/`, navegação ou rodapés.
- Não criar sitemap ou robots.txt que cite o arquivo ou suas edições.
- Manter `noindex,nofollow` no arquivo e em cada edição, sem canonical.
- `noindex,nofollow` não é controle de acesso: quem conhece a URL pode abri-la,
  e o repositório é público.
- Antes de publicar, conferir links, datas, leitura em celular e navegação por
  teclado. Não publicar o exemplo ou uma edição vazia.
- Após o deploy, verificar HTTP 200 em `/ia/newsletter/` e em cada nova edição,
  conferir a meta robots no HTML servido e confirmar que nenhuma outra página
  do site passou a apontar para o arquivo.
