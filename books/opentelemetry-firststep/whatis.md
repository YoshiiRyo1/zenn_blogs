---
title: "OpenTelemetry とは"
---

# OpenTelemetry の略称

本書では、OpenTelemetry を OTel と略します。OpenTelemetry と書くと少しだけ長いのでご了承ください。  

# OTel とは

OTel はオブサーバビリティのフレームワークとテレメトリデータを作成管理するためのツールキットです。  
OTel はベンダーやツールに依存していません。OTel はテレメトリデータの生成・収集・管理・エクスポートにフォーカスしています。
OTel 自体はオブサーバビリティバックエンドではありません。オープンソースや商用のバックエンドをあなたが選択して利用可能です。  
OTel の目標は、言語やランタイムに関係なく、アプリケーションの観測を可能にすることです。  

# メインコンポーネント

OTel の主なコンポーネントです。それぞれの詳細は別の章で説明します。  

- 仕様
- データ形式を定義する標準プロトコル
- データ型の命名スキームを定義するセマンティック規則
- API
- 各言語 SDK
- 共通のライブラリとフレームワーク計装を実装するライブラリエコシステム
- 自動計装コンポーネント
- OTel Collector
- Kubernetes 用の OpenTelemetry Operator、 OpenTelemetry Helm Charts、 FaaS 用のコミュニティ アセットなど、その他のさまざまなツール

# 拡張性

OTel は拡張可能なように設計されています。  
特定のソースからテレメトリデータを収集したり、OTLP (OTel 標準プロトコル) をサポートしていないオブサーバビリティバックエンドへのエクスポートなど、ニッチな用途でも OTel を利用できる利点があります。  

例として、大手クラウドベンダーは自らのクラウドリソースから効率的にテレメトリデータを収集するための拡張を用意しています。  

https://aws.amazon.com/jp/otel/

https://learn.microsoft.com/ja-jp/azure/azure-monitor/app/opentelemetry-enable?tabs=aspnetcore

# シグナル

シグナルは、アプリケーションやシステムのアクティビティを示す出力です。特定のある時点で計測したいもの（例えば、メモリ使用量など）や、トレースしたいイベントなどが該当します。  
OTel の目的は、これらシグナルを収集・処理・エクスポートすることと言えるでしょう。  

OTel では以下の4つをサポートしてます。  

- トレース（Traces）
- メトリクス（Metrics）
- ログ（Logs）
- バゲッジ（Baggage）

# OTel の MVV

https://opentelemetry.io/community/mission/

本ページの最後になりましたが、OTel の MVV を紹介します。  
OTel の MVV を読むと彼らの方向性や目標が理解でき、Otel に関する興味が増すはずです。  


