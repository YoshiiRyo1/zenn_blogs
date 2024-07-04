---
title: "Amazon Inspector2 Scan の Slack 通知を見やすくしてみた"
emoji: "🌊"
type: "tech" # tech: 技術記事 / idea: アイデア
topics: ["AWS", "Inspector", "Slack"]
published: true
---

こんにちは。  
ご機嫌いかがでしょうか。  
"No human labor is no human error" が大好きな[吉井 亮](https://twitter.com/YoshiiRyo1)です。  

ECR の拡張スキャンを利用しています。  
どこの実行環境であるかに関係なく、ECS 上で動かすコンテナに脆弱性を混入させないための対策です。  

ECR 拡張スキャンについては本エントリの本題ではないので割愛しますが、試験運用している限りではしっかり検出してくれている印象です。  
[Scan images for OS and programming language package vulnerabilities in Amazon ECR](https://docs.aws.amazon.com/AmazonECR/latest/userguide/image-scanning-enhanced.html)  
[Operating systems and programming languages that Amazon Inspector supports](https://docs.aws.amazon.com/inspector/latest/user/supported.html#supported-os)  

## Inspector2 Scan の Slack 通知

スキャン結果を都度都度マネジメントコンソールで確認するのは面倒です。スキャン結果のサマリーを Slack に通知するようにしました。  
[Tutorial: Get started with Slack](https://docs.aws.amazon.com/chatbot/latest/adminguide/slack-setup.html)  

ただ、少々問題がありました。デフォルト設定のままだとスキャンしたのは解るけど、なにがどうなのかが不明です。  
Chatbot にもいい感じに整形してくれるサービスとそうではないサービスがあるようです。  

![img](/images/inspector2-chatbot.png)  

## 通知のカスタマイズ

Slack 通知を希望通りカスタマイズしましょう。  
カスタマイズには EventBridge の入力トランスフォーマーを使用します。  
[Custom notifications](https://docs.aws.amazon.com/chatbot/latest/adminguide/custom-notifs.html)  

必要最低限は以下の項目だけあれば良さそうです。  
下の例にあるように、Slack の書式が使えます。頑張ればより読みやすい通知ができるはずです。  

```json
{
    "version": "1.0",
    "source": "custom",
    "content": {
        "description": ":warning: EC2 auto scaling refresh failed for ASG *OrderProcessorServiceASG*! \ncc: @SRE-Team"
    }
}
```

### 入力トランスフォーマーの設定

拡張スキャン時に発生するイベントは "Inspector2 Scan" と "Inspector2 Finding" があります。  
今回は Push On Scan 時の検出件数を知りたかったので "Inspector2 Scan" を選択しました。  

"Inspector2 Scan" のイベントの例です。  

```json:Inspectot2 Scan
{
    "version": "0",
    "id": "739c0d3c-4f02-85c7-5a88-94a9EXAMPLE",
    "detail-type": "Inspector2 Scan",
    "source": "aws.inspector2",
    "account": "123456789012",
    "time": "2021-12-03T18:03:16Z",
    "region": "us-east-2",
    "resources": [
        "arn:aws:ecr:us-east-2:123456789012:repository/amazon/amazon-ecs-sample"
    ],
    "detail": {
        "scan-status": "INITIAL_SCAN_COMPLETE",
        "repository-name": "arn:aws:ecr:us-east-2:123456789012:repository/amazon/amazon-ecs-sample",
        "finding-severity-counts": {
            "CRITICAL": 7,
            "HIGH": 61,
            "MEDIUM": 62,
            "TOTAL": 158
        },
        "image-digest": "sha256:36c7b282abd0186e01419f2e58743e1bf635808231049bbc9d77e5EXAMPLE",
        "image-tags": [
            "latest"
        ]
    }
}
```

このイベントから知りたい情報を拾って、Slack で読める形に整形します。そのために EventBridge 入力トランスフォーマーを使います。
[入力トランスフォーマー](Amazon EventBridge input transformation) には入力パスと入力テンプレートが必要です。    

```json:入力パス
{
  "ACCOUNT": "$.account",
  "COUNTS_CRITICAL": "$.detail.finding-severity-counts.CRITICAL",
  "COUNTS_HIGH": "$.detail.finding-severity-counts.HIGH",
  "COUNTS_MEDIUM": "$.detail.finding-severity-counts.MEDIUM",
  "COUNTS_TOTAL": "$.detail.finding-severity-counts.TOTAL",
  "DETAIL-TYPE": "$.detail-type",
  "REPONAME": "$.detail.repository-name",
  "TAGS": "$.detail.image-tags[0]"
}
```

```json:入力テンプレート
{
  "version": "1.0",
  "source": "custom",
  "content": {
    "description": ":information_source: *<DETAIL-TYPE> | <ACCOUNT>*\n*Scaned_Repo_Name:* <REPONAME>,\n*Tag:* <TAGS>,\n*CRITICAL:* <COUNTS_CRITICAL>,\n*HIGH:* <COUNTS_HIGH>,\n*MEDIUM:* <COUNTS_MEDIUM>,\n*TOTAL:* <COUNTS_TOTAL>"
  }
}
```

私が欲しかったのは "CRITICAL", "HIGH", "MEDIUM", "TOTAL" の検出件数です。なので "COUNT_XXX" という変数に detail.finding-severity-counts.xxx を代入しています。  
Slack の絵文字を入れたり、太字にしたり、改行を入れたりしています。"description" はかなり人間には読みにくいですね、、、  

### カスタマイズした通知

その結果、以下のような通知が来るようになりました。  
これなら Slack だけで概要が把握できます。  

![img](/images/inspector2-chatbot-custom.png)

## 参考

[Custom notifications](https://docs.aws.amazon.com/chatbot/latest/adminguide/custom-notifs.html)  
[入力トランスフォーマー](Amazon EventBridge input transformation)  
