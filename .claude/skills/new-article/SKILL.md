---
name: new-article
description: Zenn新記事を作成する。npx zenn new:article でファイルを生成し、frontmatterと定型冒頭文を自動で挿入する。
---

# Zenn 新記事作成スキル

ユーザーから以下の情報を受け取り、新しいZenn記事を作成する。

## 引数

- **タイトル**（必須）: 記事のタイトル
- **タイプ**（省略可、デフォルト: `tech`）: `tech`（技術記事）または `idea`（アイデア記事）
- **トピック**（省略可）: タグをスペースまたはカンマ区切りで指定。最大5個
- **絵文字**（省略可）: 記事のアイコン絵文字。省略時はタイトルから適切なものを選ぶ

## 手順

1. `npx zenn new:article` を実行して記事ファイルを生成する

```bash
npx zenn new:article
```

2. 生成されたファイルを特定する（`articles/` 配下で最も新しい `.md` ファイル）

3. ファイルを以下のテンプレートで上書きする

```markdown
---
title: "{{タイトル}}"
emoji: "{{絵文字}}"
type: "{{タイプ}}" # tech: 技術記事 / idea: アイデア
topics: [{{トピック}}]
published: false
---
こんにちは。
ご機嫌いかがでしょうか。
"No human labor is no human error" が大好きな[吉井 亮](https://twitter.com/YoshiiRyo1)です。

<!-- ここから本文を書く -->
```

4. 作成したファイルのパスをユーザーに伝え、プレビューコマンドを案内する

```
作成しました: articles/xxxxxxxxxx.md

プレビュー: npx zenn preview
```

## 注意事項

- `published: false` で作成し、公開前に手動で `true` に変更してもらう
- トピックが指定されない場合は空配列 `[]` にせず、タイトルから推測して提案する
- トピック文字列はZennの仕様に合わせ、英数字・ハイフンのみ使用（日本語トピック不可）
- 絵文字はUnicode絵文字1文字のみ
