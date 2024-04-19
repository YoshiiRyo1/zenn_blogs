---
title: "Collector"
---

# Collector

![colletor](https://opentelemetry.io/docs/collector/img/otel-collector.svg)  
*https://opentelemetry.io/docs/collector/ より*

Collector はテレメトリーデータをベンダー依存ではない方法で Receive、Process、Export します。  
図にあるように、Receivers、Processors、Extensions、Exporters といったコンポーネントを組み合わせて構成されています。  
複数のエージェントを使用して、トレースはこっちへ送る、メトリクスはあっちへ送る、といったことをせずに Collector に送り Collector で処理してそれぞれの Observability Backend へ送信することが可能です。  

## Collector インストール

さまざまな方法で Collector をインストールできます。  

- [Docker Image](https://hub.docker.com/r/otel/opentelemetry-collector-contrib)
- Helm Chart, Helm Operator
- Nomad
- Linux 各種 Package
- tarball されたバイナル
- ソースからビルド

詳しくは以下を参照ください。  
[Install the Collector](https://opentelemetry.io/docs/collector/installation/)   

## Deployment パターン

インストールにはさまざまな方法があるように、デプロイメントにもさまざまなパターンがあります。  

### Collector 無し

Collector を使用しないパターンです。  
アプリケーションに Otel SDK を組み込み、直接 Observability Backend に送信します。  

Collector が不要なので、インフラ構成はシンプルになります。  

何か変更があるたびにコードを修正しなければならないのが最大の難点です。本来的な作業ではありません。  
本番環境とそれ以外の実行環境で異なる設定を持つことになるはずなので、その管理も手間かもしれません。  

言語ごとの実装の違いにも注意が必要です。  
[Language APIs & SDKs](https://opentelemetry.io/docs/languages/)  

現実的には Agent か Gateway の何れかを採用することが多いのでないでしょうか。  

### Agent

アプリケーションと Otel Collector が同じホストで動作するパターンです。おおむね 1対1 の関係になると思います。  
Sidecar または Daemonset と表現したほうが分かりやすいかもしれません。  

アプリケーション内の Otel SDK は Collector にテレメトリーシグナルを送信し、Collector が Observability Backend に送信します。  

Gateway に比べるとシンプルな構成です。まず Otel を試す方には向いているかもしれません。  
1対1に近い形で Sidecar or Daemonset が必要なので、その分のリソースオーバーヘッドは考慮する必要があります。  

![img](https://opentelemetry.io/docs/collector/img/otel-agent-sdk.svg)  
*https://opentelemetry.io/docs/collector/deployment/agent/ より*

### Gateway

Collector 群を独立したサービスとして構成します。  
アプリケーション内の Otel SDK と Collector の間には Load Balancer を設置し、トラフィックを分散します。  

Agent のようなサービスごとの Collector デプロイは不要になりましたが、Gateway としての Collector 運用管理が必要になり、かつ、少々難易度が上がります。  
Agent 時のオーバーヘッドと Gateway 時のオーバーヘッドを比較して、どちらが適しているかを検討する必要があります。  
 
![img](https://opentelemetry.io/docs/collector/img/otel-gateway-sdk.svg)  
*https://opentelemetry.io/docs/collector/deployment/gateway/ より*  

# 設定ファイル

Collector の設定ファイルは yaml 形式で記述します。  

デフォルトでは `/etc/otelcol-contrib/config.yaml` や `/etc/otelcol/config.yaml` に置いてあります。  

独自のパスに保存している場合は起動時のオプションでパスを指定します。  

```bash
otelcol --config /path/to/config.yaml
```

## 設定ファイル記述方法

設定ファイルの記述方法を読み解いてみます。  

### 0.0.0.0 は避ける

主に receivers だと思いますが、バインドするアドレスがデフォルトでは `0.0.0.0` となっています。  

```yaml
receivers:
  otlp:
    protocols:
      grpc:
        endpoint: 0.0.0.0:4317
```

便利である一方、DoS 攻撃を受けてしまう可能性があります。  
安全に Collector を運用するために `localhost` などに変更することをお勧めします。  

```yaml
receivers:
  otlp:
    protocols:
      grpc:
        endpoint: localhost:4317


# MY_POD_IP を環境変数から取得する例
receivers:
  otlp:
    protocols:
      grpc:
        endpoint: ${env:MY_POD_IP}:4317
```

### Type/Name フォーマット

receivers, processors, exporters などの YAML トップレベルタグの下、セカンドレベルのタグにはコンポーネント名を記述します。  
同じコンポーネントを複数配置したい場合は、Type/Name フォーマットで記述します。  

以下は otlp exporter を複数配置する例です。  

```yaml
exporters:
  otlp:
    endpoint: backend:4317
  otlp/2:
    endpoint: otherbackend:4317
```

### receivers

Receiver は Collector がテレメトリーシグナルを受信するためのコンポーネントを定義します。  
Collector には1つ以上の receiver が必要です。  

```yaml
receivers:
# otlp receiver
  otlp:
    protocols:
      grpc:
        endpoint: ${env:MY_POD_IP}:4317
      http:
        endpoint: ${env:MY_POD_IP}:4318

# jaeger receiver
  jaeger:
    protocols:
      grpc:
        endpoint: 0.0.0.0:4317

# Tailing a simple json file
  filelog:
    include: [ /var/log/myservice/*.json ]
    operators:
      - type: json_parser
        timestamp:
          parse_from: attributes.time
          layout: '%Y-%m-%d %H:%M:%S'
```

多くのコンポーネントが提供されています。要件にあったものを選択し、適切に設定します。  
提供されているコンポーネントは以下を参照ください。  

[opentelemetry-collector/receiver](https://github.com/open-telemetry/opentelemetry-collector/tree/main/receiver)  
[opentelemetry-collector-contrib/receiver](https://github.com/open-telemetry/opentelemetry-collector-contrib/tree/main/receiver)  

安全ではないネットワークで Collector を運用する場合には認証を実装可能です。  
[Authentication configuration](https://github.com/open-telemetry/opentelemetry-collector/blob/main/config/configauth/README.md)  

また、コンポーネントを自作することも可能です。  
[Building a receiver](https://opentelemetry.io/docs/collector/building/receiver/)  

### processors

Processor はテレメトリーデータを変換、フィルタリング、リダクションするためのコンポーネントを定義します。  
receivers で受信したテレメトリーシグナルにメタデータを付与したり、Obversability Backend で活用しやすいようにデータを加工します。  

processors で属性を付与しておくと Observability Backend での検索が容易になります。ダッシュボード作成時にも便利です。  

`batch` と `memory_limiter` は必須くらいの気持ちでいいと思います。  

```yaml
processors:
# 圧縮を行いデータ量を減らす、全ての Collector で使いましょう
  batch: {}

# Out of Memory を防ぐためにメモリーに制限をかけます
  memory_limiter:
    check_interval: 5s
    limit_percentage: 80
    spike_limit_percentage: 25

# Example of adding k8s attributes to the resource
  k8sattributes:
    passthrough: false
    extract:
      metadata:
        - k8s.namespace.name
        - k8s.deployment.name
        - k8s.statefulset.name
        - k8s.daemonset.name
        - k8s.cronjob.name
        - k8s.job.name
        - k8s.node.name
        - k8s.pod.name
        - k8s.pod.uid
        - k8s.pod.start_time
    pod_association:
      - sources:
        - from: resource_attribute
          name: k8s.pod.ip
      - sources:
        - from: resource_attribute
          name: k8s.pod.uid
      - sources:
        - from: connection

# Example of adding service.name as a label to Loki   
  resource/loki:
    attributes:
      - action: insert
        key: loki.format
        value: json
      - action: insert
        key: loki.resource.labels
        value: service.name

# service.name が "service1" のスパンは Drop します
  filter/ottl:
    error_mode: ignore
    traces:
      span:
        - resource.attributes["service.name"] == "service1"
```

提供されているコンポーネントは以下を参照ください。  

[opentelemetry-collector/processor](https://github.com/open-telemetry/opentelemetry-collector/tree/main/processor)  
[opentelemetry-collector-contrib/processor](https://github.com/open-telemetry/opentelemetry-collector-contrib/tree/main/processor)  

### exporters

Exporter はテレメトリーデータを Observability Backend または、他の宛先に送信するためのコンポーネントを定義します。  
push 型（Collector から相手へ送信する）、pull 型（相手から Collector に取りに来る）のどちらも可能です。Prometheus は pull 型で構成することがありますね。  

通信はデフォルトで gzip 圧縮されます。圧縮アルゴリズムは選択可能です。  
[compression comparison](https://github.com/open-telemetry/opentelemetry-collector/blob/main/config/configgrpc/README.md#compression-comparison)  

```yaml
exporters:
# Troubleshooting
  debug:

  file:
    path: ./filename.json

  otlp:
    endpoint: backend:4317
    compression: zstd

  otlp/jaeger:
    endpoint: jaeger-server:4317
      cert_file: cert.pem
      key_file: cert-key.pem

  otlp/tempo:
    endpoint: tempo:4317
    tls:
      insecure: true

  prometheus:
    endpoint: 0.0.0.0:8889
```

提供されているコンポーネントは以下を参照ください。  

[opentelemetry-collector/exporter](https://github.com/open-telemetry/opentelemetry-collector/tree/main/exporter)  
[opentelemetry-collector-contrib/exporter](https://github.com/open-telemetry/opentelemetry-collector-contrib/tree/main/exporter)  

### connectors

Connector は2つの Pipeline を接続する役割を果たします。  

![img](https://opentelemetry.io/docs/collector/img/otel-collector-after-connector.png)  
*https://opentelemetry.io/docs/collector/building/connector/ より*

よく使うのは `Count Connector` です。    
[README](https://github.com/open-telemetry/opentelemetry-collector-contrib/tree/main/connector/countconnector) に記載されている例は、Trace から Metrics へ接続しています。
これによって、`trace.span.count` と `trace.span.event.count` というメトリクスを生成しています。    

```yaml
receivers:
  foo:
exporters:
  bar:
connectors:
  count:

service:
  pipelines:
    traces/in:
      receivers: [foo]
      exporters: [count]
    metrics/out:
      receivers: [count]
      exporters: [bar]
```

提供されているコンポーネントは以下を参照ください。  

[opentelemetry-collector/connector](https://github.com/open-telemetry/opentelemetry-collector/tree/main/connector)  
[opentelemetry-collector-contrib/connector](https://github.com/open-telemetry/opentelemetry-collector-contrib/tree/main/connector)  

### extensions

Collector の機能を拡張します。テレメトリーシグナルの処理とは直接関係ないですが、Collector 運用に便利なものなので活用していきましょう。  
`health_check` は必須、それ以外は問題発生時に役立つものです。  

```yaml
extensions:
# Collector の診断に使います
  zpages:
    endpoint: ${env:MY_POD_IP}:55679
# ヘルスチェックに使える HTTP エンドポイントを提供します
  health_check:
    endpoint: ${env:MY_POD_IP}:13133
# Performance Profiler, 主にパフォーマンス問題のデバッグに使います
  pprof:
    endpoint: ${env:MY_POD_IP}:1777
```

[opentelemetry-collector/extension](https://github.com/open-telemetry/opentelemetry-collector/tree/main/extension)  
[opentelemetry-collector-contrib/extension](https://github.com/open-telemetry/opentelemetry-collector-contrib/tree/main/extension)  

### service

service セクションでは、これまでに紹介したコンポーネントを組み合わせてパイプラインを構築します。パイプラインを構成することでテレメトリーシグナルを受信し、処理し、エクスポートすることが可能になります。  

`pipelines` サブセクションは特によく触れる箇所だと思います。`traces` -> `processors` -> `exporters` の順で記述します。ここに記述する前に使うコンポーネントは定義しておきましょう。  


```yaml
service:
  telemetry:
    metrics:
      address: ${env:MY_POD_IP}:8888
  extensions:
    - health_check
    - pprof
    - zpages
  pipelines:
    logs:
      exporters:
        - loki
      processors:
        - memory_limiter
        - k8sattributes
        - batch
      receivers:
        - filelog
    metrics:
      exporters:
        - prometheus
      processors:
        - memory_limiter
        - k8sattributes
        - filter/ottl
        - transform
        - batch
      receivers:
        - otlp
        - prometheus
    traces:
      exporters:
        - otlp
      processors:
        - memory_limiter
        - k8sattributes
        - batch
      receivers:
        - otlp
```

# 参考

[opentelemetry-collector](https://github.com/open-telemetry/opentelemetry-collector)  
[opentelemetry-collector-contrib](https://github.com/open-telemetry/opentelemetry-collector-contrib)
