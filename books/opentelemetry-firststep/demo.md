---
title: "デモ"
---

# デモ

デモを触りながら Collector および What is Observability について理解していきましょう。  
公式のデモを使用します。  
[OpenTelemetry Demo Documentation](https://opentelemetry.io/docs/demo/)  

本書では、ローカル PC に Kubernetes クラスタを立ち上げてデモを実行します。  

# kubectl インストール

Kubernetes クラスタを操作するために kubectl をインストールします。  

[Install Tools](https://kubernetes.io/docs/tasks/tools/) に OS ごとのインストール方法が記載されています。それを参照してインストールしましょう。  

# minikube インストール

今回は minikube を使用します。  
minikube はローカル環境で Kubernetes クラスタを起動できるツールです。  

インストール手順は [minikube start](https://minikube.sigs.k8s.io/docs/start/) を参照ください。  

```bash
$ brew install minikube

# help が表示されることを確認
$ minikube --help
minikube は、開発ワークフロー用に最適化されたローカル Kubernetes クラスターを構築・管理します。
〜〜省略〜〜
```

minikube を起動するのですが、デモの動作環境メモリが「6 GB of free RAM for the application」なので、minikube 起動時にメモリ容量を 6GB 以上に指定します。  

```bash
$ minikube start --cpus='4' --memory='8G'
```

 [Helm](https://opentelemetry.io/docs/demo/kubernetes-deployment/#install-using-helm-recommended) を使用してデモをインストールします。  

```bash
$ helm repo add open-telemetry https://open-telemetry.github.io/opentelemetry-helm-charts

$ helm install my-otel-demo open-telemetry/opentelemetry-demo -n otel-demo --create-namespace
NAME: my-otel-demo
LAST DEPLOYED: Tue Apr 23 14:06:38 2024
NAMESPACE: otel-demo
STATUS: deployed
REVISION: 1
NOTES:
=======================================================================================


 ██████╗ ████████╗███████╗██╗         ██████╗ ███████╗███╗   ███╗ ██████╗
██╔═══██╗╚══██╔══╝██╔════╝██║         ██╔══██╗██╔════╝████╗ ████║██╔═══██╗
██║   ██║   ██║   █████╗  ██║         ██║  ██║█████╗  ██╔████╔██║██║   ██║
██║   ██║   ██║   ██╔══╝  ██║         ██║  ██║██╔══╝  ██║╚██╔╝██║██║   ██║
╚██████╔╝   ██║   ███████╗███████╗    ██████╔╝███████╗██║ ╚═╝ ██║╚██████╔╝
 ╚═════╝    ╚═╝   ╚══════╝╚══════╝    ╚═════╝ ╚══════╝╚═╝     ╚═╝ ╚═════╝


- All services are available via the Frontend proxy: http://localhost:8080
  by running these commands:
     kubectl --namespace otel-demo port-forward svc/my-otel-demo-frontendproxy 8080:8080

  The following services are available at these paths once the proxy is exposed:
  Webstore             http://localhost:8080/
  Grafana              http://localhost:8080/grafana/
  Load Generator UI    http://localhost:8080/loadgen/
  Jaeger UI            http://localhost:8080/jaeger/ui/
```

Pod が起動していることを確認します。  
すべての Pod STATUS が Running になるまで待ちます。数分かかります。  

```bash
$ kubectl get pod -n otel-demo
```

`helm install` の出力で提示されているコマンドを実行すると、デモの Web UI にアクセスできます。  
別ターミナルを開いてコマンドを実行します。  

```bash
$ kubectl --namespace otel-demo port-forward svc/my-otel-demo-frontendproxy 8080:8080
# 以下の2行で表示されていれば OK
Forwarding from 127.0.0.1:8080 -> 8080
Forwarding from [::1]:8080 -> 8080
```

# Grafana

Grafana は Observability Platform です。  
採用しているサイトは多いのではないでしょうか。人気あるツールです。  

Grafana を開いてみましょう。  
http://localhost:8080/grafana/  

デモでは4つのダッシュボードが用意されています。  

- Demo Dashboard
- OpenTelemetry Collector
- Opentelemetry Collector Data Flow
- Spanmetrics Demo Dashboard

`Demo Dashboard` は参考になる作りになっていると感じました。サービスごとに観たい、という用途に合います。  

![grafana](/images/opentelemetry-firststep/grafana01.png)  

メニューから Home → Explore を辿ると収集しているシグナルを検索可能です。  

![grafana](/images/opentelemetry-firststep/grafana02.png)  

# Load Generator

LOCUST はオープンソースの Load Testing ツールです。  
Python でシナリオを記述できるツールです。  

LOCUST を開いてみましょう。  
http://localhost:8080/loadgen/  

このデモで使用されているシナリオはこちらを参照ください。  
[Load generator source](https://github.com/open-telemetry/opentelemetry-demo/tree/main/src/loadgenerator)

Load Generator UI を開くとテストの統計画面が開きます。  
すこし眺めていると数字が増えていき、テストが実行されている様子が確認できます。  
※ テストを流したままにすると PC が重くなるのでデモを体験していない時はテストを停止することをオススメします。  

![locust](/images/opentelemetry-firststep/locust01.png)

Charts タブからは統計を確認できます。  

![locust](/images/opentelemetry-firststep/locust02.png)

# Jaeger 

Jaeger は Uber Technologies 社が開発した分散トレーシングシステムです。  
有名なプロダクトなので聞いたことある方も多いかと思います。  

それでは Jaeger を使いながらトレースを勉強していきましょう。  
http://localhost:8080/jaeger/ui/  

最初は Search 画面が開くと思います。  
任意のサービスを選択して **Find Traces** をクリックしてください。  

![jaeger](/images/opentelemetry-firststep/jaeger01.png)  


右側にいくつかトレースが表示されると思います。そのうちの1つをクリックしてください。  
親スパンと子スパンが表示されると思います。なんとなくトレースに強くなった気がしてきませんか？    

![jaeger](/images/opentelemetry-firststep/jaeger02.png)  

どれか1つ子スパンをクリックして展開してみてください。  
属性が表示されました。これにより子スパンがどこでなにを実行したものかが判明します。  
Tags と Process という属性名ですが、Grafana では Span と Resource という名前で表示されます。  

![jaeger](/images/opentelemetry-firststep/jaeger03.png)  

# トラブルシュートシナリオ

さて、Kubernetes クラスタにデモが立ち上がりました。  
デモを表示させただけではつまらないので、トラブルシュートをしてみましょう。  

デモが起動している場合は一旦落とします。  

```bash
$ helm delete my-otel-demo -n otel-demo
release "my-otel-demo" uninstalled

# 落ちたことの確認、数分かかります
$ kubectl get pod -n otel-demo
```

何種類かトラブルシュート用の設定が用意されています。  
[Feature Flags](https://opentelemetry.io/docs/demo/feature-flags/)  
今回は `Product Catalog` で Product ID `OLJCESPC7Z` が取得できないシナリオを試します。  

少し設定をいじりますので、chart をダウンロードして展開します。  

```bash
$ helm pull open-telemetry/opentelemetry-demo
$ tar -zxf opentelemetry-demo-0.30.2.tgz
```

`opentelemetry-demo/flagd/demo.flagd.json` を編集します。  
このファイルには Feature Flags 一式が記載されています。`productCatalogFailure` を **on** に変更します。  

```json
    "productCatalogFailure": {
      "description": "Fail product catalog service on a specific product",
      "state": "ENABLED",
      "variants": {
        "on": true,
        "off": false
      },
      "defaultVariant": "on"
    },
```

変更したらローカルに保存した chart を使ってデモを起動します。  

```bash
$ helm install my-otel-demo ./opentelemetry-demo -n otel-demo --create-namespace

# 正常起動確認、数分かかります
$ kubectl get pod -n otel-demo
```
別ターミナルを開いてコマンドを実行します。  

```bash
$ kubectl --namespace otel-demo port-forward svc/my-otel-demo-frontendproxy 8080:8080
# 以下の2行で表示されていれば OK
Forwarding from 127.0.0.1:8080 -> 8080
Forwarding from [::1]:8080 -> 8080
```

最初に Grafana のダッシュボードを確認してみましょう。  

URL は以下です。  

```
  Webstore             http://localhost:8080/
  Grafana              http://localhost:8080/grafana/
  Load Generator UI    http://localhost:8080/loadgen/
  Jaeger UI            http://localhost:8080/jaeger/ui/
```

Grafana メニューから **Home** → **Dashboards** → **Spanmetrics Demo Dashboard** を選択します。  
**span_names Level - Throughput** という列を探してください。  

![troubleshoot](/images/opentelemetry-firststep/snanmetrice_red.png)  

明らかにエラー率が高いスパン `grpc.oteldemo.ProductCatalogService/GetProduct` が見つかります。  


次に Jaeger でトレースを確認してみましょう。  
Search 画面で Service 欄から `productcatalogservice` を選択して Find Traces をクリックしてください。  
赤丸になっているトレースが表示されます。これがエラーになっているトレースです。  

![troubleshoot](/images/opentelemetry-firststep/spanerror.png)  

どれもよいのでエラーのトレースをクリックします。  
親スパンをクリックし、さらに Logs を展開すると Span Event が記録されています。ここで原因が判明します。  
今回はわざとエラーになるようにしているので「13 INTERNAL: Error: ProductCatalogService Fail Feature Flag Enabled」といったメッセージがが確認できます。  

![troubleshoot](/images/opentelemetry-firststep/spanevent.png)

[ソースコード](https://github.com/open-telemetry/opentelemetry-demo/blob/main/src/productcatalogservice/main.go) でしっかりと Span Event を記録していることが確認できます。  

```go
	// GetProduct will fail on a specific product when feature flag is enabled
	if p.checkProductFailure(ctx, req.Id) {
		msg := fmt.Sprintf("Error: ProductCatalogService Fail Feature Flag Enabled")
		span.SetStatus(otelcodes.Error, msg)
		span.AddEvent(msg)
		return nil, status.Errorf(codes.Internal, msg)
	}
```

ダッシュボードから兆候を見つけて、トレースで原因までたどり着く手順を体験できたと思います。  

## クリーンアップ

デモが起動している場合はクリーンアップしておきます。  

```bash
$ helm delete my-otel-demo -n otel-demo
release "my-otel-demo" uninstalled

# 落ちたことの確認、数分かかります
$ kubectl get pod
```






