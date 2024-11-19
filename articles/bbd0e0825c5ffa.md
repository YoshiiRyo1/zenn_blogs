---
title: "Chatbot から Amazon Q Developer と会話しながら Ops してみた"
emoji: "👌"
type: "tech" # tech: 技術記事 / idea: アイデア
topics: ["AWS", "ChatOps", "Chatbot", "AmazonQDeveloper"]
published: true
---
こんにちは。  
ご機嫌いかがでしょうか。  
"No human labor is no human error" が大好きな[吉井 亮](https://twitter.com/YoshiiRyo1)です。  

AWS Chatbot が Slack or Teams で Amazon Q Developer とのチャットをサポートするようになりました。  
最近 ChatOps にのめり込んでいるので、実運用で活用できるのかを試してみました。  

https://aws.amazon.com/jp/about-aws/whats-new/2024/11/aws-chatbot-chatting-amazon-q-developer-microsoft-teams-slack/

## セットアップ

Chatbot 自体のセットアップは [Getting started with AWS Chatbot](https://docs.aws.amazon.com/chatbot/latest/adminguide/getting-started.html) を参照ください。  

Chatbot でチャンネルに設定した IAM ロール (チャンネルロール) とガードレールのポリシーにマネージドポリシーの `AmazonQDeveloperAccess` を追加します。チャンネルロールとガードレールの両方に必要ですのでご注意ください。  

リソースに対してより良く情報を引き出すために Resource Explorer をセットアップしておく必要があるようです。
必要な方は、チャンネルロールとガードレールに `AWSResourceExplorerReadOnlyAccess` ポリシーを追加します。  
[Asking AWS Chatbot AWS resource questions in chat channels](https://docs.aws.amazon.com/chatbot/latest/adminguide/resource-questions-cbt.html)  

## やってみた

それでは早速チャットしてみます。スクショだと見えてはいけないものが見えそうなので、テキストを貼り付けます。    

### Show EC2 instances

アカウント内の起動中 EC2 インスタンスをリストします。  
これはユーザーガイドの例に記載されているものです。  

```
me: @aws show ec2 instances in running state in ap-northeast-1

aws: You have 1 EC2 instance in the running state in the ap-northeast-1 region.
The instance details are:
  • Instance ID: i-xxxxxxxxxxxxxxxxx
  • Instance Type: m6g.medium
  • Availability Zone: ap-northeast-1a
  • Public IP Address: nnn.nnn.nnn.nnn
  • Private IP Address: nnn.nnn.nnn.nnn

i-xxxxxxxxxxxxxxxxx
arn:aws:ec2:ap-northeast-1:aws_account_id:instance/i-xxxxxxxxxxxxxxxxx
Amazon Elastic Compute Cloud    Region: ap-northeast-1

Related resources:
Amazon Elastic Compute Cloud
vol-xxxxxxxxxxxxxxxxx
```

起動中のインスタンスが表示されました。このアカウントでは1台起動中ですので、正しく表示されているようです。  
回答は3回に分かれて表示されました。1回目はインスタンス ID やインスタンスタイプ、IP アドレスなどの基本的な情報、2回目は ARN、
3回目は関連リソースが表示されました。EBS ボリュームのみです。EIP、VPC やセキュリティグループは表示されませんでした。  

2回目と3回目の回答には「View JSON」ボタンが表示されています。これをクリックすると、JSON 形式で情報が表示されます。Describe-instances と Describe-volumes の結果のようです。  
より詳しい情報を知りたい場合は「View JSON」ボタンをクリックするのが良さそうです。  

### Show RDS clusters

似たような質問で次は RDS クラスターを表示してみます。  

```
me: @aws show RDS Clusters in running state in ap-northeast-1

aws: You have one Amazon Aurora DB cluster named "cluster-production" in the ap-northeast-1 region that is in the "available" state. 
The cluster is configured with multi-AZ deployment across three availability zones (ap-northeast-1c, ap-northeast-1a, ap-northeast-1d) and has two DB instances associated with it.

cluster-production
arn:aws:rds:ap-northeast-1:aws_account_id:cluster:cluster-production
Amazon Relational Database Service    Region: ap-northeast-1
{
  省略
}
```

EC2 と違い ID や IP アドレスは表示されませんでした。クラスターがマルチ AZ で構成されていて2つの DB インスタンスがあることが文章で表示されました。  
また、「View JSON」ボタンはなく、直接 JSON が表示されました。Describe-db-clusters の結果のようです。  

### CPU 使用率を表示

EC2 インスタンスの CPU 使用率を表示してみます。  

```
me: @aws show CPU utilization of arn:aws:ec2:ap-northeast-1:aws_account_id:instance/i-xxxxxxxxxxxxxxxxx

aws: Could you please provide the desired start and end times, period, and statistics for the CPU utilization metric?
```

EC2 インスタンスの ARN を指定して質問してみました。Chatbot からは CPU 使用率を表示する期間を指定を求められました。当然と言えば当然ですね。  


```
me: @aws show CPU utilization of arn:aws:ec2:ap-northeast-1:aws_account_id:instance/i-xxxxxxxxxxxxxxxxx since 30 minutes ago

aws: I have retrieved the CPU utilization metric for the specified instance over the past 30 minutes. The data is as follows:
  • Average: 1.67%
  • Maximum: 3.33%
  • Minimum: 0.00%
The data is collected every minute, and the statistics are calculated based on the 'Average' metric. The data points are as follows:
  • 01:00: 1.67%
  • 01:01: 1.67%
  • 01:02: 1.67%
  • 01:03: 1.67%
  • 01:04: 1.67%
  • 01:05: 1.67%
  • 01:06: 1.67%
  • 01:07: 1.67%
  • 01:08: 1.67%
  • 01:09: 1.67%
  • 01:10: 1.67%
  • 01:11: 1.67%
  • 01:12: 1.67%
  • 01:13: 1.67%
  • 01:14: 1.67%
  • 01:15: 1.67%
  • 01:16: 1.67%
  • 01:17: 1.67%
  • 01:18: 1.67%
  • 01:19: 1.67%
  • 01:20: 1.67%
  • 01:21: 1.67%
  • 01:22: 1.67%
  • 01:23: 1.67%
  • 01:24: 1.67%
  • 01:25: 1.67%

Sources
 [1]  Identify the EC2 instances with the most CPU utilization | AWS re:Post
 [2]  UtilizationMetric - AWS Compute Optimizer
 [3]  Monitor CPU burst capacity for your Lightsail instance - Amazon Lightsail
```

英語を勉強しないといけないと感じました笑。  
この質問をしたのが10時45分ころだったのですが、10時00分から25分間の CPU 使用率が表示されました。  

希望の時間帯の CPU 使用率を表示するには、開始時間と終了時間を明示的に指定する必要がありそうです。  

```
me: @aws show CPU utilization of arn:aws:ec2:ap-northeast-1:aws_account_id:instance/i-xxxxxxxxxxxxxxxxx from "2024-11-19T01:20:00Z" to "2024-11-19T01:25:00Z"

aws: I have retrieved the CPU utilization metric for the specified instance between "2024-11-19T01:20:00Z" and "2024-11-19T01:25:00Z". The data is as follows:
  • Average: 1.67%
  • Maximum: 3.33%
  • Minimum: 0.00%
The data points are as follows:
  • 01:20: 1.67%
  • 01:21: 1.67%
  • 01:22: 1.67%
  • 01:23: 1.67%
  • 01:24: 1.67%
  • 01:25: 1.67%
```

はい、完璧です。10時20分〜25分のメトリクスが表示されました。  


### CloudWatch Alarm の状態を表示

CloudWatch Alarm の状態を表示してみます。現在アラーム状態になっているアラームがあるのかどうかを知りたい場面はあると思います。  

```
me: @aws show name of CloudWatch Alarm with status is In alarm

aws: The name of the CloudWatch Alarm with status "In alarm" in the ap-northeast-1 region is:
"Step-Scaling-AlarmLow-ECS:/xxxxxxxxxxxxx"
Step-Scaling-AlarmLow-ECS
arn:aws:cloudwatch:ap-northeast-1:aws_account_id:alarm:Step-Scaling-AlarmLow-ECS:/xxxxxxxxxxxxx
Amazon CloudWatch    Region: ap-northeast-1
{
  省略
}
```

アラーム状態になっている CloudWatch Alarm の名前が表示されました。システム障害が発生していることを知る手段の1つとして使えそうです。  

ところで、このアラームはいつからアラーム状態なのでしょうか？  

```
me: @aws when did this Alarm become "In alarm"

aws: Sorry, there were no Amazon CloudWatch resources in ap-northeast-1 that matched your query.
```

聞き方が悪そうです。連続する会話をする方法を探さなければなりません。会話しながらより詳細化することができたらもっともっと便利になるはずです。  

試しに1つの質問に盛り込んでみました。  

```
me: @aws show name of CloudWatch Alarm with status is In alarm and when that Alarm become "In alarm"

aws: The CloudWatch Alarm with status "In alarm" is "Step-Scaling-AlarmLow-ECS:/xxxxxxxxxxxxx" in the ap-northeast-1 region.
This alarm became "In alarm" on August 14, 2024 at 05:50:57 UTC.
```

おお〜、August 14, 2024 at 05:50:57 UTC からアラーム状態になっていることがわかりました👏👏👏

### コストの表示

今月の AWS 利用料金を尋ねてみます。  

```
me: @aws show total costs of this month in ap-northeast-1.

aws: I'm sorry, but I cannot provide real-time or specific information about your AWS account, 
such as your monthly costs in the ap-northeast-1 region. However, 
I can guide you on how to access this information through the AWS Management Console.
```

コスト表示は無理そうです。できたら嬉しかったけど今後に期待でしょうか。  

### ざっくりとした質問

ざっくりとした質問をしてみます。期待としては、CloudTrail を検索してくれてサマってくれると嬉しいです。  

```
me: @aws are there any recently created AWS resources in ap-northeast-1?

aws: I'm sorry, but I cannot directly provide information about specific AWS resources or their creation times. 
However, I can help you create a script or use AWS CLI commands to retrieve this information.

Here's a general example using AWS CLI:
  1. Install and configure AWS CLI on your local machine if you haven't already.
  2. Run the following command to list resources in the ap-northeast-1 region:
aws cloudtrail describe-trails --region ap-northeast-1
省略
```

期待通りの回答ではありませんが、CLI をインストールして CloudTrail を検索する手順は教えてくれました。無茶ぶりにも親切に対応してくれる Amazon Q Developer です。  

### 日本語

最後に日本語を試してみます。  

```
me: @aws 東京リージョンで起動しているEC2インスタンスを教えて

aws:  Invalid input. Valid characters are A-Z a-z . , ? ! @ & : ; ( ) ' " - . Exception: C++ and C# are allowed.
```

まぁそうですよね。Exception は気になりますが。  


## まとめ

AWS リソースに関する質問にも答えてくれる Amazon Q Developer を使ってみました。  
契約している AWS アカウント内の情報を自然言語で教えてくれるのは便利です。Ops にも AI を活用する時代がもうすでに来ています。
Amazon Q Developer で Ops を進化させてハッピーになりたいと思います。  

## 宣伝

12月11日に OpsJAWS Meetup32 を開催します。  
re:Invent 期間中に発表されたものを含む新機能やアップデート情報を共有します。ハイブリッド開催となっております。是非ご参加ください。  

https://opsjaws.connpass.com/event/336277/

