---
title: "Amazon Bedrock Agents と Chatbot で無敵のOpsになる"
emoji: "💪"
type: "tech" # tech: 技術記事 / idea: アイデア
topics: ["AIOps", "AWS", "Bedrock", "Chatbot"]
published: true
---
こんにちは。  
ご機嫌いかがでしょうか。  
"No human labor is no human error" が大好きな[吉井 亮](https://twitter.com/YoshiiRyo1)です。  

アメリカ時間 2024年9月17日のアップデートで、Chatbot による Slack または Teams から Bedrock Agent と対話できるようになりました。  

https://aws.amazon.com/jp/about-aws/whats-new/2024/09/aws-chatbot-amazon-bedrock-agent-microsoft-teams-slack/

多くの人が検証しアウトプットしています。私も遅れながらも本番環境へ導入したので記事にします。  

## メリット

私が考える一番のメリットは、チャットで完結することです。  
欲しい情報を各種コンソールや API などで取得するのは多少の手間が発生します。また、非エンジニアのメンバーに各種コンソールの操作をしてもらうのは忍びない気持ちです。  
チャットであれば、あるチャンネルに入ってさえいれば簡単に情報を引き出せます。  

LLM ならではの深い洞察も魅力です。調べたい事柄に必要なデータを可能な限り大量に与えることで、より深い洞察を得ることができると思います。
人間では時間がかかる複数ログの数百行のデータを瞬時に解析してくれます。  
ハルシネーションはあるので LLM の回答を鵜呑みにすることはでませんが、判断材料として使えますし、ハルシネーションがあるのは人間も一緒です。  

## 準備

### Chatbot との連携

さあ、それでは導入していきましょう。  

私の環境は Slack なので、Slack での導入方法を記載します。  

AWS Chatbot と Slack を連携します。本題ではないので割愛します。以下の記事を参考にしてください。  

https://aws.amazon.com/jp/builders-flash/202006/slack-chatbot/

### ECR の作成

ひとまず ECR リポジトリを作成します。  


```terraform:main.tf
terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "5.68.0"
    }
  }
}

provider "aws" {
  region = "ap-northeast-1"
}

locals {
  app_name = "your_app_name"
}

resource "aws_ecr_repository" "my_repository" {
  name = local.app_name
}
```

```bash
terraform init
terraform plan
terraform apply
```

## モデルアクセスの付与

今回は `Claude 3.5 Sonnet` を使います。  
[Add or remove access to Amazon Bedrock foundation models](https://docs.aws.amazon.com/bedrock/latest/userguide/model-access-modify.html) を参考にモデルアクセスを付与しましょう。
難しいことはありません。簡単な質問に答えるだけです。


## Lambda 関数用コンテナイメージの作成

Agent から Lambda 関数を呼び出します。この関数は Amazon Pinpoint からプッシュ通知開封率を取得します。あまりメジャーではないサービスですが、どういうデータを取得してどうやってレスポンスを返すのか参考にしてもらえると嬉しいです。  

### handler

handler です。使い回しができるようにシンプルに作っています。  

```pythion:lambda_function.py
import json
import get_openrate
import os
import logging

logger = logging.getLogger()
logger.setLevel("INFO")

def handler(event, context):
    logger.info("Initializing Function...")

    applicationId = os.environ["APPLICATION_ID"]
    kpiName = os.environ["KPI_NAME"]

    res = get_openrate.get_openrate(applicationId, kpiName)
    logger.info("Open rate: %s", res)

    session_attributes = event['sessionAttributes']
    prompt_session_attributes = event['promptSessionAttributes']

    response = {
        "actionGroup": event["actionGroup"],
        "function": event["function"],
        "functionResponse": {
            "responseBody": {
                "TEXT": {"body": json.dumps({"response": res}, ensure_ascii=False)}
            }
        },
    }
    api_response = {
        "messageVersion": "1.0",
        "response": response,
        "sessionAttributes": session_attributes,
        "promptSessionAttributes": prompt_session_attributes
    }

```

<br />  
response, api_response に注目してください。 Bedrock Agent から Lambda 関数へのリクエストとレスポンスは決まった形式が定義されています。その形式でレスポンスを返してあげます。  
[Configure Lambda functions to send information an Amazon Bedrock agent elicits from the user](https://docs.aws.amazon.com/bedrock/latest/userguide/agents-lambda.html)  

### データ取得

Pinpoint からデータを取得する部分です。  
ここを CloudWatch なり S3 なりなんなりによしなに変更すれば、横展開が可能だと思います。無駄なデータを削減しつつ大量なデータを与えてあげることがコツだと感じています。
DB に例えるなら、列は選別しつつ行は大量に取得する感じです。  

ここでも注目するのはレスポンスです。途中コメントしてある出力例の形式（項目は任意ですが、JSON 形式という意味です）でリターンしてあげることが大事です。    

```python:get_openrate.py
import boto3
import sys
import logging

logger = logging.getLogger()
logger.setLevel("INFO")

def get_openrate(applicationId, kpiName):
  pinpoint = boto3.client('pinpoint')

  logger.info("Getting open rate for applicationId: %s, kpiName: %s", applicationId, kpiName)

  # キャンペーン一覧を取得
  try:
    resCampaigns = pinpoint.get_campaigns(
      ApplicationId=applicationId,
      PageSize='30'
    )
  except Exception as e:
    logger.error("API Failed: get_campaigns")
    logger.error("Error getting campaigns: %s", e)
    sys.exit(1)

  # 出力例
  # [
  #   {
  #     "LastModifiedDate": "The date, in ISO 8601 format",
  #     "Key": "The key of the KPI",
  #     "Value": "The value of the KPI",
  #     "Type": "The type of the KPI"
  #   }
  # ]
  resList = []
  campaignItem = resCampaigns['CampaignsResponse']['Item']
  for item in campaignItem:
    # CampaignStatus が COMPLETED なら開封率を取得
    if item['State']['CampaignStatus'] == 'COMPLETED':
      try:
        logger.info("Getting KPI for campaignId: %s", item['Id'])

        resKpi = pinpoint.get_campaign_date_range_kpi(
          ApplicationId=applicationId,
          CampaignId=item['Id'],
          KpiName=kpiName
        )

        # resKpi['CampaignDateRangeKpiResponse']['KpiResult']['Rows']がNullでなければ
        if resKpi['CampaignDateRangeKpiResponse']['KpiResult']['Rows']:
          tempDict = {
            "LastModifiedDate": item['LastModifiedDate'],
            "Key": resKpi['CampaignDateRangeKpiResponse']['KpiResult']['Rows'][0]['Values'][0]['Key'],
            "Value": resKpi['CampaignDateRangeKpiResponse']['KpiResult']['Rows'][0]['Values'][0]['Value'],
            "Type": resKpi['CampaignDateRangeKpiResponse']['KpiResult']['Rows'][0]['Values'][0]['Type']
          }
          logger.info("Dict: %s", tempDict)
          resList.append(tempDict)
        logger.info("Finish Getting KPI: %s", item['Id'])
      except Exception as e:
        logger.error("API Failed: get_campaign_date_range_kpi")
        logger.error("Error getting KPI: %s", e)
        sys.exit(1)

  return resList
```


### ローカルでテスト

私は Lambda 関数の開発はもっぱら Docker で行っています。  

```yaml:compose.yml
services:
  your_app_name:
    build: .
    env_file: .env
    platform: linux/amd64
    ports:
      - "9000:8080"
    environment:
      - APPLICATION_ID=pinpoint-application-id
      - KPI_NAME=push-open-rate
      - AWS_REGION=ap-northeast-1
```

```Dockerfile:Dockerfile
FROM public.ecr.aws/lambda/python:3.12

# Copy requirements.txt
COPY requirements.txt ${LAMBDA_TASK_ROOT}

# Install the specified packages
RUN pip install -r requirements.txt

# Copy function code
COPY lambda_function.py get_openrate.py ${LAMBDA_TASK_ROOT}

# Set the CMD to your handler (could also be done as a parameter override outside of the Dockerfile)
CMD [ "lambda_function.handler" ]
```

```text:requirements.txt
boto3
```


ローカルで実行します。  

```bash
env | grep AWS > .env

docker compose build
docker compose up -d

curl "http://localhost:9000/2015-03-31/functions/function/invocations" -d '
{
    "messageVersion": "1.0",
    "function": "local-function",
    "actionGroup": "action-group-quick-start",
    "sessionAttributes": {
        "string": "string"
    },
    "promptSessionAttributes": {
        "string": "string"
    }
}'

{"messageVersion": "1.0", "response": {"actionGroup": "action-group-quick-start", "function": "local-function", "functionResponse": {"responseBody": {"TEXT": {"body": "{\"response\": [{\"LastModifiedDate\": \"2024-09-19T08:20:21.369Z\", \"Key\": \"PushOpenRate\", \"Value\": \"0.02040816326530612\", 省略}]}"}}}}, "sessionAttributes": {"string": "string"}, "promptSessionAttributes": {"string": "string"}}
```

期待通りのレスポンスが返ってきました。  
ローカルで動作確認できたので ECR レスポンスに Push しましょう。  
your_aws_account, your_app_name は適宜変更してください。  

```bash
aws ecr get-login-password --region ap-northeast-1 | docker login --username AWS --password-stdin your_aws_account.dkr.ecr.ap-northeast-1.amazonaws.com
docker build -t your_app_name .
docker tag your_app_name:latest your_aws_account.dkr.ecr.ap-northeast-1.amazonaws.com/your_app_name:latest
docker push your_aws_account.dkr.ecr.ap-northeast-1.amazonaws.com/your_app_name:latest
```

## AWS リソースの作成

ECR リポジトリを作成した tf ファイルに他の AWS リソースを追加します。  

resouce `aws_bedrockagent_agent` 内にある `instruction` には、Agent に対してどのような指示を出すか、いわゆるプロンプトを記載します。ここをどう書くかが腕の見せどころです。  
各変数やパラメーターは適宜変更してください。  

```terraform:main.tf
terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "5.68.0"
    }
  }
}

provider "aws" {
  region = "ap-northeast-1"
}

data "aws_region" "current" {}

data "aws_caller_identity" "current" {}

locals {
  app_name   = "your_app_name"
  agent_name = "your_agent_name"
}

resource "aws_ecr_repository" "my_repository" {
  name = local.app_name
}

resource "aws_iam_role" "my_backend_role" {
  name = "${local.app_name}-backend-role"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Principal = {
          Service = "lambda.amazonaws.com"
        }
        Action = "sts:AssumeRole"
      }
    ]
  })

  managed_policy_arns = [
    "arn:aws:iam::aws:policy/service-role/AWSLambdaBasicExecutionRole",
    "arn:aws:iam::aws:policy/AmazonEC2ContainerRegistryPowerUser",
  ]
}

resource "aws_iam_role_policy" "my_backend_role_policy" {
  name = "${local.app_name}-backend-role-policy"
  role = aws_iam_role.my_backend_role.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid    = "AllowPinpoint"
        Effect = "Allow"
        Action = [
          "mobiletargeting:GetCampaigns",
          "mobiletargeting:GetCampaignVersions",
          "mobiletargeting:GetCampaignActivities",
          "mobiletargeting:GetCampaignDateRangeKpi"
        ]
        Resource = "*"
      }
    ]
  })
}

resource "aws_iam_role" "my_bedrock_role" {
  name = "${local.app_name}-bedrock-role"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Principal = {
          Service = "bedrock.amazonaws.com"
        }
        Action = "sts:AssumeRole"
        Condition = {
          StringEquals = {
            "aws:SourceAccount" = data.aws_caller_identity.current.account_id
          }
          ArnLike = {
            "aws:SourceArn" = "arn:aws:bedrock:${data.aws_region.current.name}:${data.aws_caller_identity.current.account_id}:agent/*"
          }
        }
      }
    ]
  })
}

resource "aws_iam_role_policy" "my_bedrock_role_policy" {
  name = "${local.app_name}-bedrock-role-policy"
  role = aws_iam_role.my_bedrock_role.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid    = "AmazonBedrockAgentBedrockFoundationModelPolicy"
        Effect = "Allow"
        Action = [
          "bedrock:InvokeModel"
        ]
        Resource = "arn:aws:bedrock:${data.aws_region.current.name}::foundation-model/anthropic.claude-3-5-sonnet-20240620-v1:0"
      },
      {
        Sid    = "InvokeLambda"
        Effect = "Allow"
        Action = [
          "lambda:InvokeFunction"
        ]
        Resource = "arn:aws:lambda:${data.aws_region.current.name}:${data.aws_caller_identity.current.account_id}:function:${local.app_name}"
      }
    ]
  })
}

resource "aws_cloudwatch_log_group" "cwlogs_openrate" {
  name              = "/aws/lambda/${local.app_name}"
  retention_in_days = 14
}

resource "aws_lambda_function" "my_function" {
  function_name = local.app_name
  role          = aws_iam_role.my_backend_role.arn
  image_uri     = aws_ecr_repository.my_repository.repository_url
  package_type  = "Image"
  architectures = ["x86_64"]

  environment {
    variables = {
      APPLICATION_ID = "pinpoint-application-id"
      KPI_NAME       = "push-open-rate"
    }
  }

  timeout = 300
}

resource "aws_lambda_permission" "aws_permission" {
  statement_id   = "AllowExecutionFromBedrock"
  action         = "lambda:InvokeFunction"
  function_name  = aws_lambda_function.my_function.function_name
  principal      = "bedrock.amazonaws.com"
  source_account = data.aws_caller_identity.current.account_id
  source_arn     = aws_iam_role.my_bedrock_role.arn
}

resource "aws_bedrockagent_agent" "my_agents_bedrock" {
  agent_name              = local.agent_name
  agent_resource_role_arn = aws_iam_role.my_bedrock_role.arn
  foundation_model        = "anthropic.claude-3-5-sonnet-20240620-v1:0"
  instruction             = <<EOF
私達はスマートフォン向けアプリケーションを提供しています。このアプリケーションはカバディチームの公式アプリケーションです。
あなたはアプリケーションへプッシュ通知を行うマーケティング担当者です。アプリケーションのユーザーに対してプッシュ通知を行うために、Amazon Pinpointを使用しています。
プッシュ通知後、ユーザーのプッシュ通知を開いた割合を確認する必要があります。 プッシュ通知開封率は{get_my_api}から取得できます。{LastModifiedDate}が最新のものと全ての平均を報告してください。
また、一般的なスマートフォン向けスポーツクラブアプリケーションのプッシュ通知開封率の平均を推定し、今回のプッシュ通知開封率と比較してください。 報告は全て日本語でお願いします。
プッシュ通知開封率は Value に Double 型で格納されています。
回答例を参考に可能な限り簡潔に回答してください。
回答例: 最新のプッシュ通知開封率は {n}% でした。
スポーツクラブアプリケーションと比較すると 'かなり低め|低め|平均的|高め|かなり高め' です。
直近のプッシュ開封率平均は {n}% です。
EOF
}

resource "aws_bedrockagent_agent_action_group" "my_agent_action_group" {
  action_group_name = "get_my_api"
  agent_id          = aws_bedrockagent_agent.my_agents_bedrock.id
  agent_version     = "DRAFT"

  action_group_executor {
    lambda = aws_lambda_function.my_function.arn
  }

  function_schema {
    member_functions {
      functions {
        name = "get_my_api"
      }
    }
  }
}

resource "aws_bedrockagent_agent_alias" "my_agent_alias" {
  agent_alias_name = "v1"
  agent_id         = aws_bedrockagent_agent.my_agents_bedrock.id
}
```

動かなったらごめんなさい。試行錯誤してもらえばと思います。  

```bash
terraform plan
terraform apply
```

## マネジメントコンソールでテスト

マネジメントコンソールから Bedrock を開きます。  

作成したエージェントをクリックします。  

画面右側にテスト用エリアがあります。ここでテストを行います。任意の文字列を入力して、期待の応答が返ってくるか確認します。  

![alt text](/images/bedrock_agent_test.png)

### エイリアス

テストが成功したら画面下部にあるエイリアス ID をコピーしておきます。後で Slack で使います。  
（ところで何故エイリアス名が使えないのでしょうか・・・ ID よりエイリアス名のほうが便利ですよね）

![alt text](/images/bedcork_agent_alias.png)

## Slack でテスト

Chatbot と連携したチャンネルで以下のコマンドを実行します。  
your_agent_arn と your_alias_id は適宜変更してください。  

```
@aws connector add connector_name your_agent_arn your_alias_id
```

成功すると以下のようなレスポンスが返ってきます。  

```
:火: Connector successfully added.
To chat with what you've connected, ask something like the following:

@aws ask connector_name - what things should I ask you?
```

それではテストしてみましょう。身バレを避けるために一部ぼかしています。  

```
me: @aws ask connector_name  最新の開封率を教えて

chatbot: 最新のプッシュ通知開封率は20.38%でした。スポーツクラブアプリケーションと比較すると高めです。直近のプッシュ開封率平均も20.38%です。

me: @aws スポーツクラブアプリケーションの平均的な開封率は？

chatbot: 一般的なスポーツクラブアプリケーションの平均的なプッシュ通知開封率は約25-30%程度と推定されます。
最新のプッシュ通知開封率は20.38%でした。スポーツクラブアプリケーションと比較すると低めです。直近のプッシュ開封率平均も20.38%です。

me: @aws 詳細情報とリンクなどもあればお願いします

chatbot: 詳細情報とリンク：
1. 一般的なモバイルアプリのプッシュ通知開封率：
  - 平均して20-30%程度
  - 出典: https:/hogehoge
```

会話の始めは `@aws ask connector_name 聞きたいこと` で始めます。スレッドで返答がきます。そのまま会話を続けたい場合は `@aws` にメンションしながら会話すれば OK です。  
1回目は平均と比べて "高め” なのに、2回目では "低め” と返ってきました。この辺りはまだ仕方のないところかなと思います。  
詳細情報とリンクも教えてくれましたが、全部 404 Not Found でした(笑)。ご愛嬌です。  


[Invoking Amazon Bedrock Agents from chat channels in AWS Chatbot](https://docs.aws.amazon.com/chatbot/latest/adminguide/connect-bedrock-agents.html)  

## 参考

とても参考になりました。ゼロ知識から開始したので五里霧中だったのですが、こちらの記事を読んで理解が深まりました。感謝です。  

https://www.sbcr.jp/product/4815626440/

https://aws.amazon.com/jp/blogs/mt/enable-cloud-operations-workflows-with-generative-ai-using-agents-for-amazon-bedrock-and-amazon-cloudwatch-logs/  

https://aws.amazon.com/jp/blogs/news/failure-analysis-assistant-aiops/  

https://dev.classmethod.jp/articles/agents-for-amazon-bedrock-set-up-function-details/  



