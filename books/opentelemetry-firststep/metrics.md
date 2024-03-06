---
title: "メトリクス"
---

# メトリクス

メトリクスは、実行されているサービスのある時点の測定値です。測定時、タイムスタンプ、メタデータで構成されます。  

メトリクスはアプリケーションのパフォーマンスや可用性がユーザー体験やビジネスに与える影響に対する洞察を提供します。  
また、クラスターシステムの FailOver、オートスケーリングのトリガー、アラートのトリガー、リソースの使用状況の監視などの様々な用途に利用されます。  

```
+------------------+
| MeterProvider    |                 +-----------------+             +--------------+      +----------------+
|   Meter          | Measurements... |                 | Metrics...  |              | Pare |                |
|     Instrument   +-----------------> View            +-------------> MetricReader +------> MetricExporter +-----> Another process
|                  |                 |                 |             |              |      |                |
|                  |                 +-----------------+             +--------------+      +----------------+
+------------------+
```

# Meter Provider

Meter Provider は API のエントリポイントです。  
公式にある図が理解しやすいので引用します。  

```
+-- MeterProvider(default)
    |
    +-- Meter(name='io.opentelemetry.runtime', version='1.0.0')
    |   |
    |   +-- Instrument<Asynchronous Gauge, int>(name='cpython.gc', attributes=['generation'], unit='kB')
    |   |
    |   +-- instruments...
    |
    +-- Meter(name='io.opentelemetry.contrib.mongodb.client', version='2.3.0')
        |
        +-- Instrument<Counter, int>(name='client.exception', attributes=['type'], unit='1')
        |
        +-- Instrument<Histogram, double>(name='client.duration', attributes=['server.address', 'server.port'], unit='ms')
        |
        +-- instruments...

+-- MeterProvider(custom)
    |
    +-- Meter(name='bank.payment', version='23.3.5')
        |
        +-- instruments...
```
*https://opentelemetry.io/docs/specs/otel/metrics/api/ より*

# Meter

Meter は Meter Provider により作成されます。そして、Meter は Metric Instruments を作成し測定値をキャプチャーします。  

# Metric Exporter

測定値をコンシューマに送信します。Otel Collector やオブサーバビリティバックエンドなどがコンシューマになります。また、標準出力に出力し開発中のデバッグに利用することもできます。  

# Instrument

測定値をキャプチャーするのが Instrument です。  
各 Instruments は以下の情報を持ちます。  

- Name
- Kind
  - Counter
  - Asynchronous Counter
  - UpDownCounter
  - Asynchronous UpDownCounter
  - Gauge
  - Histogram
- Unit (optional)
- Description (optional)


## Name

Instruments 名は以下の規則に従います。  

- Null や空文字列は許可されません
- 大文字小文字を区別しない ASCII 文字列
- 最初の文字はアルファベットでなければなりません
- 2文字目以降は、アルファベット、数字、_（アンダースコア）、．（ドット）、-（ハイフン）、/（スラッシュ）が使用できます
- 文字列の長さは最大255文字です

## Kind

測定値には種類があります。ほしいデータを正しく収集するには種類を正しく選択する必要があります。  
[Supplementary Guidelines](https://opentelemetry.io/docs/specs/otel/metrics/supplementary-guidelines/) に種類の選択基準が記載されています。  

- 何かをカウントしたい（デルタ値を記録する場合）：
  - 値が単調に増加する場合（デルタ値は常に非負）- `Counter` を使用します。
  - 値が単調に増加しない場合（デルタ値は正、負、またはゼロになる可能性がある）- `UpDownCounter` を使用します。
- 何かを記録または計測し、その統計情報が意味を持つ可能性がある場合 - `Histogram` を使用します。
- 何かを計測したい（絶対値を報告する場合）：
  - 計測値が非加算的な場合、`Asynchronous Gauge` を使用します。
  - 計測値が加算的な場合：
    - 値が単調に増加する場合 - `Asynchronous Counter` を使用します。
    - 値が単調に増加しない場合 - `Asynchronous UpDownCounter` を使用します。


### Counter

単調に増加する累積的な測定値です。馴染深いと思います。  
例えば、リエクストカウント、送受信データ量、エラーカウントなどです。  

### Asynchronous Counter

Asynchronous Counter は定間隔で測定値を取得する使い方をします。  
公式に記載されている例だと、CPU time や Page Faults の取得とされているように、アプリケーションロジックとは同期しない、かつ、単勝に増加する測定値を取得する場合に使われます。  

### UpDownCounter

増減する測定値です。アクティブリクエスト数、キューのなかのアイテム数が例示されています。
キューにアイテムが入れば +1、キューから取り出されれば -1 といった具合です。  

### Asynchronous UpDownCounter

増減する測定値を非同期に取得します。ヒープサイズ、ロックフリー循環バッファのアイテム数が例示されています。  

### Asynchronous Gauge

Gauge は非加算な測定値です。無作為に増減するものに最適です。公式の例だと、室温や CPU fan spped が示されています。  

### Histogram

統計的に意味がある形式で計測値を記録します。  

リクエストレスポンスタイムを例にしてみます。  
バケットと呼ばれる境界値で区切られた範囲ごとに統計されて計測値が記録されます。  

| レスポンスタイム | 0時0分 | 0時1分 | 0時2分 |
| ---------------- | ------ | ------ | ------ |
| 0 - 5ms          | 10     | 20     | 30     |
| 5 - 10ms         | 5      | 10     | 15     |
| 10 - 15ms        | 3      | 6      | 9      |

## Unit

単位です。ASCII 文字で大文字小文字は区別されます。kb と KB は違う単位として認識されます。  

## Description

説明文です。基本多言語面でサポートされている文字を使います。一般的な文字、アルファベットやひらがな、カタカナなどが使えます。顔文字や音符などは使えません。  
最大1023文字までとなっています。  

# おまけ

メトリクスに関してはいまいちシックリきてないので、もう少し調べてみます。

