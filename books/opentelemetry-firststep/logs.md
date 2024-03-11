---
title: "ログ"
---

# ログ

ログは構造化された、または、非構造のテキストデータです。ログにはメタデータとタイムスタンプが含まれます。  
IT システムの世界では大昔からログは重要な情報源として利用されてきました。ほとんどのプログラム言語には組み込むのログ機能やロギングライブラリが備わっています。  
OTel では、分散トレースまたはメトリックの一部ではないデータはすべてログと考えているようです。  

OTel のログはトレースやメトリクスとは違うアプローチを採用しています。ログはプログラム言語や運用ソリューションにすでに広く普及しています。
それらの既存ログ、トレース、メトリクス、および、他の OTel コンポーネントのブリッジとして機能するアプローチとなっています。  

![img](https://opentelemetry.io/docs/specs/otel/logs/img/appender.png)  
*https://opentelemetry.io/docs/specs/otel/logs/supplementary-guidelines/ より*

# OTel のログソリューション

様々なオブサーバビリティソリューションが存在しますが、シグナル同士の統合が不十分であることが多いです。
トレースを取得・表示するソリューション、メトリクスを取得・表示ソリューションなど個別の実装は多いですが、それぞれの相関関係に弱さを持っています。  

OTel データモデルに準拠した方法でログ、メトリクス、トレースを出力すると、それぞれの相関関係が強化されます。分散システムにおけるログの価値が高まります。  

以下に例示したログのように、traceID や spanID が付与されていると Obserbability Backend のトレース画面でトレースからログを表示することが可能です。  

```json:トレースやリソースが付与されたログ例
{
  "body": "Completed 200 OK",
  "traceid": "49466a9bac62b7894bec62a03110c04b",
  "spanid": "57017b19ec784c59",
  "severity": "DEBUG",
  "flags": 1,
  "resources": {
    "container.id": "d14bbe139661ca673f940be6fefcd67764a0eef512642ef79992a5b170d48484",
    "host.arch": "aarch64",
    "host.name": "d14bbe139661",
    "os.description": "Linux 6.6.16-linuxkit",
    "os.type": "linux",
    "process.command_args": [
      "/usr/lib/jvm/java-17-openjdk-arm64/bin/java",
      "-jar",
      "/app/build/libs/app.jar"
    ],
    "process.executable.path": "/usr/lib/jvm/java-17-openjdk-arm64/bin/java",
    "process.pid": 1,
    "process.runtime.description": "Debian OpenJDK 64-Bit Server VM 17.0.10+7-Debian-1deb11u1",
    "process.runtime.name": "OpenJDK Runtime Environment",
    "process.runtime.version": "17.0.10+7-Debian-1deb11u1",
    "service.name": "demo",
    "telemetry.distro.name": "opentelemetry-java-instrumentation",
    "telemetry.distro.version": "2.1.0",
    "telemetry.sdk.language": "java",
    "telemetry.sdk.name": "opentelemetry",
    "telemetry.sdk.version": "1.35.0"
  },
  "instrumentation_scope": {
    "name": "org.springframework.web.servlet.DispatcherServlet"
  }
}
```

# ログレコード

OTel ログレコードには2種類のフィールドが含まれます。  

- 特定のタイプと意味を持つトップレベルフィールド
- 任意の値とタイプで構成されたリソースと属性


トップレベルフィールド

| Field Name           | Description                                                                                                                            |
| -------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Timestamp            | イベント発生時のタイムスタンプ                                                                                                         |
| ObservedTimestamp    | イベントが観測された時のタイムスタンプ。Timestamp が存在する場合は Timestamp を使用。                                                  |
| TraceId              | トレース ID。このフィールドはオプションです。                                                                                          |
| SpanId               | スパン ID。このフィールドはオプションです。                                                                                            |
| TraceFlags           | [W3C trace flag](https://www.w3.org/TR/trace-context/#trace-flags)。このフィールドはオプションです。                                   |
| SeverityText         | 重要度のテキスト表示。このフィールドはオプションです。                                                                                 |
| SeverityNumber       | 重要度の数値表示。このフィールドはオプションです。                                                                                     |
| Body                 | アプリケーションが生成したログ本文。このフィールドはオプションです。                                                                   |
| Resource             | ログソースを示す情報。このフィールドはオプションです。                                                                                 |
| InstrumentationScope | 発行されてテレメトリの範囲を示す論理ユニット。開発者の任意の選択。計装ライブラリを指定するのが一般的。このフィールドはオプションです。 |
| Attributes           | イベントの追加情報。このフィールドはオプションです。                                                                                   |

