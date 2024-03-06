---
title: "トレース"
---

# トレース

トレースは、アプリケーションがリクエストを受けたときに内部的に何が起きているかを我々に教えてくれます。  
アプリケーションがモノリスだろうが、メッシュされたマイクロサービスだろうが、トレースはアプリケーションの動作を理解するための重要なシグナルです。  

# 分散トレース

分散トレース（一般的にトレースと呼びます）はあるリクエストが複数のマイクロサービスを通過して伝搬されるパスを示しています。分散トレースはリクエストの全体像を示しています。  
分散トレース無しでは、分散システムのパフォーマンス問題を特定することは難しいでしょう。  

分散トレースは複数のスパンで構成されています。  
ルートスパン（下図 `client` ）は最初から最後までのリクエストを表しています。  
ルートスパンの下には複数の親子関係のスパンが含まれます。  
下図だと `/api` が子スパンです。その子スパンとして `/autN`、`/paymentGateway`、`/dispatch` があります。さらにそれぞれには子スパンがあります。  
例えば、`/api` が 1ms だとしたら、 `/autN` は 0.15ms、`/paymentGateway` は 0.35ms、`/dispatch` は 0.5ms といった具合で処理時間が分かります。ボトルネックがどのサブシステムなのかが発見しやすくなります。  

![](https://opentelemetry.io/img/waterfall-trace.svg)  
*https://opentelemetry.io/img/waterfall-trace.svg より*

各スパンは親スパンの ID を持っており、これをリクエストと同時に伝搬していきます。そうすることにより、オブサーバビリティバックエンドは上図のような分散トレースの視覚化が可能になります。  

# スパン

スパンは処理の単位です。自動計装の場合は、スパンは自動的に生成されます。
手動計装ではソースコードの任意の場所でスパンを生成します。（Start〜End のように範囲を指定）  

OTel のスパンには以下の情報を含めることができます。  

## name

スパン名。SDK で Create Span するときに指定します。  
RPC メソッド名、関数名など処理を簡潔に表す名前を指定することが望ましいです。また、汎用的・一般的ですべきです。`get_user` はふさわしい名称です。`get_user/12345` はカーディナリティが高いため（一般的ではなくなるため）避けるべきです。  

## Parent span ID

親スパン ID。自分自身が親スパンの場合は空です。  

## start timestamp と end timestamp

Create|End Span したときに SDK により自動的に記録されます。  

## Span Context

context には以下の情報を含むことができます。

- TraceId
- SpanId
- TraceFlags
- TraceState

自分が属するトレースの ID と自分自身のスパン ID が含まれます。これが伝搬していくことで親子関係を示すようになります。  

TraceFlags は全てのスパンに含まれます。執筆時点の仕様では [Sampled flag](https://www.w3.org/TR/trace-context/#sampled-flag) のみがサポートされています。  
このフラグはスパンがサンプリングされていることを示しています。`True` の場合 Span Exporter はスパンをエクスポートします。`False` の場合はエクスポートされません。  

TraceState はベンダー固有のトレース識別データを持ちます。Key-Value のペアです。
これにより複数のベンダーが同じトレースを追跡することができます。  

## Attributes

スパンにメタデータを付与できます。Attributes は Key-Value のペアです。  
これが大切な情報で、オブサーバビリティバックエンドでトレースやスパンを検索したときに、何がどこで起きているかを示すデータになったり、検索対象にできたりします。  

このページをスクロールしたところに出力サンプルを記載しています。`attributes` の項目を見てください。ネットワーク情報や HTTP メソッド、ユーザーエージェントなどが含まれています。
Kubernetes ならば Pod 名、コンテナ名、ノード名などを含めることができます。  

[Semantic Attributes](https://opentelemetry.io/docs/specs/semconv/general/trace/) に定義されている命名規則に従って属性を付与することが望ましいです。  

## Span Events

Span Events はスパン内に記録できるログメッセージです。スパン中に発生したイベントを記録します。  
例えば、OTel Java 自動計装エージェントはトレース中に例外をキャッチするとそれを Span Event として記録します。  

Span Events には以下の情報が含まれます。 

- イベントの名前
- イベント発生時のタイムスタンプ
- イベントの内容を示す属性

## 例外

トレースを手動計装する場合でも、例外をキャッチして Span Event として記録すべきとされています。  
公式にあるコード例のように Try-Catch-Finally で実装しましょう。  

```
Span span = myTracer.startSpan(/*...*/);
try {
  // Code that does the actual work which the Span represents
} catch (Throwable e) {
  span.recordException(e, Attributes.of("exception.escaped", true));
  throw e;
} finally {
  span.end();
}
```
*https://opentelemetry.io/docs/specs/otel/trace/exceptions/ より*


また、exception イベント用の属性も一緒に付与すると良いと思います。  

- exception.escaped
- exception.message
- exception.stacktrace
- exception.type

属性の詳細は [Semantic Conventions for Exceptions on Spans](https://opentelemetry.io/docs/specs/semconv/exceptions/exceptions-spans/#attributes) を参照ください。  

## Span Links

他のスパンへのリンクです。スパン間の前後関係を示すのに有効です。  
あるリクエストがバッチ処理される、非同期処理されるといった後続処理がいつ実行されるか分からないケースで活用します。  

## Span Status

Span Status は `Unset`、`OK`、`Error` のいずれかです。  

`Unset` は正常に完了したことを示しています。  
これがデフォルト値であり、通常スパンが正常に完了しているのであれば Span Status は設定する必要はありません。  

`Error` は追跡中の処理で何らかのエラーが発生したことを示しています。例えば、リクエストが HTTP 500 エラーになったなどです。
`Error` にセットした場合、`Description` にメッセージを書き込みます。  

`Ok` は開発者が明示的に指定するステータスです。少し解釈が難しいのですが、何らかの意図で処理はエラーだけどスパン上は正常にしたいケースで使うことになるはずです。  

## Span Kind

スパンが作成されると Kind が以下の何れかになります。  

- SERVER
  - 同期 RPC またはリモートリクエストのサーバーサイドであることを示しています。多くの場合、CLIENT の子スパンです。
- CLIENT
  - あるリモートサービスへのリクエストです。多くの場合、SERVER の親スパンで、SERVER からの応答を受け取るまで終了しません。
- PRODUCER
  - スパンが非同期リクエストのイニシエーターであることを示しています。CONSUMER が対応完了する前、または、対応前に終了することが多いです。
- CONSUMER
  - PRODUCER の子スパンであることを示しています。
- INTERNAL
  - デフォルト値。親子を持つ操作ではなく、アプリケーションの内部操作を示しています。

親子関係（CLIENT〜SERVER、PRODUCER〜CONSUMER）、および、同期 or 非同期をオブサーバビリティバックエンドが解釈しやすいようにするために使用します。  

## サンプル

*https://opentelemetry.io/docs/concepts/signals/traces/#span-events より*

```json
{
  "name": "/v1/sys/health",
  "context": {
    "trace_id": "7bba9f33312b3dbb8b2c2c62bb7abe2d",
    "span_id": "086e83747d0e381e"
  },
  "parent_id": "",
  "start_time": "2021-10-22 16:04:01.209458162 +0000 UTC",
  "end_time": "2021-10-22 16:04:01.209514132 +0000 UTC",
  "status_code": "STATUS_CODE_OK",
  "status_message": "",
  "attributes": {
    "net.transport": "IP.TCP",
    "net.peer.ip": "172.17.0.1",
    "net.peer.port": "51820",
    "net.host.ip": "10.177.2.152",
    "net.host.port": "26040",
    "http.method": "GET",
    "http.target": "/v1/sys/health",
    "http.server_name": "mortar-gateway",
    "http.route": "/v1/sys/health",
    "http.user_agent": "Consul Health Check",
    "http.scheme": "http",
    "http.host": "10.177.2.152:26040",
    "http.flavor": "1.1"
  },
  "events": [
    {
      "name": "",
      "message": "OK",
      "timestamp": "2021-10-22 16:04:01.209512872 +0000 UTC"
    }
  ]
}
```

# Tracer Provider

Tracer Provider は Tracer を生成したり、設定を保持します。  

設定には、サンプリング、Exporter の IP アドレス/ホスト名、リミットなどが含まれます。  

# Tracer

Tracer はスパンを生成します。

# Trace Exporters

Trace Exporters はトレースをコンシューマに送信します。  
コンシューマはOTel Collector、オブサーバビリティバックエンドなどです。  

# Context の伝搬

分散トレースを実現するために Context の伝搬は欠かせない要素です。Context が伝搬していくことによって分散システムでもトレースを追跡することができます。  
伝搬については別の章で説明します。    

# 参考

https://opentelemetry.io/docs/concepts/signals/traces/

https://opentelemetry.io/docs/specs/otel/trace/

