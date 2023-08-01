# Zenn CLI

* [📘 How to use](https://zenn.dev/zenn/articles/zenn-cli-guide)

## 記事の作成

以下のコマンドによりmarkdownファイルを簡単に作成できます。

```bash
$ npx zenn new:article
```

## プレビューする

本文の執筆は、ブラウザでプレビューしながら確認できます。ブラウザでプレビューするためには次のコマンドを実行します。

```bash
$ npx zenn preview # プレビュー開始
```


[ZennのMarkdown記法一覧](https://zenn.dev/zenn/articles/markdown-guide)

## 画像ファイルの配置ルール

画像ファイルはリポジトリ直下の /images ディレクトリに配置します。 /images ディレクトリの中の構造に制限はありませんが、拡張子だけはチェック対象となります。  

```
.
├─ articles
│  └── example-article-1.md
└─ images
   ├── example-image1.png
   └── example-article-1
       └─ image1.png
```

## 画像ファイルの制限事項

画像ファイルの制限は以下の通りです。違反する場合はデプロイ時にエラーとなります。

- ファイルサイズは 3MB 以内
- 対応する拡張子は .png .jpg .jpeg .gif .webp のみ

## 画像の参照方法

画像は、記事の本文や、本のチャプターの本文から参照することができます。参照するには、画像埋め込み記法の URL 部分に /images/ から始まる絶対パスを指定します。相対パスで指定しないようご注意ください。

```
# 正しい指定方法

![](/images/example-image1.png)
![](/images/example-article-1/image1.png)

# 誤った指定方法

![](../images/example-image1.png)
![](//images/example-image1.png)
```

