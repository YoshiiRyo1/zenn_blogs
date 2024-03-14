---
title: "リソース"
---

# リソース

シグナルにリソース属性を追加することが可能です。シグナルが生成された元（実体）を示します。  
以下は手元の Docker Compose 検証環境で取得したリソース属性の一例です。  

|                             |                                                                                  |
| --------------------------- | -------------------------------------------------------------------------------- |
| container.id                | "ac05a13a94f28ef115be958f7ead279f664f89920c2ebd18e9156393e425f0f5"               |
| host.arch                   | "aarch64"                                                                        |
| host.name                   | "ac05a13a94f2"                                                                   |
| os.description              | "Linux 6.6.16-linuxkit"                                                          |
| os.type                     | "linux"                                                                          |
| process.command_args        | ["/usr/lib/jvm/java-17-openjdk-arm64/bin/java","-jar","/app/build/libs/app.jar"] |
| process.executable.path     | "/usr/lib/jvm/java-17-openjdk-arm64/bin/java"                                    |
| process.pid                 | 1                                                                                |
| process.runtime.description | "Debian OpenJDK 64-Bit Server VM 17.0.10+7-Debian-1deb11u1"                      |
| process.runtime.name        | "OpenJDK Runtime Environment"                                                    |
| process.runtime.version     | "17.0.10+7-Debian-1deb11u1"                                                      |
| service.name                | "demo"                                                                           |
| telemetry.distro.name       | "opentelemetry-java-instrumentation"                                             |
| telemetry.distro.version    | "2.1.0"                                                                          |
| telemetry.sdk.language      | "java"                                                                           |
| telemetry.sdk.name          | "opentelemetry"                                                                  |
| telemetry.sdk.version       | "1.35.0"                                                                         |


Observability Backend で調査を行う際に役に立つ情報です。例えば、ある Pod で問題が発生したとすると、pod.name で追跡をします。  

リソース属性は TraceProvider や MetricsProvider によって付与されます。なかには OTel SDK によって付与される[リソース属性](https://opentelemetry.io/docs/specs/semconv/resource/#semantic-attributes-with-sdk-provided-default-value)も存在します。上のテーブルでは `service.name` 以下がそれです。  
代表的なものが `service.name` です。環境変数 `OTEL_SERVICE_NAME` で指定しておきましょう。サービスを識別する分散システム内で一意な文字列です。  


# Detectors

ほとんどの言語の SDK にはリソース情報を自動的に検出するための Detector が用意されています。Java の場合は [OpenTelemetry Resource
](https://github.com/open-telemetry/opentelemetry-java/tree/main/sdk-extensions/autoconfigure#opentelemetry-resource) を参照ください。  
自動計装エージェントを使用していればリソース属性は自動的に付与されます。環境変数 `OTEL_JAVA_ENABLED_RESOURCE_PROVIDERS` や `OTEL_JAVA_DISABLED_RESOURCE_PROVIDERS` を使って Detector の有効無効を制御できます。  

当たり前ですが [Cloud-Provider-Specific Attributes](https://opentelemetry.io/docs/specs/semconv/resource/#cloud-provider-specific-attributes) もあり、クラウドベンダー固有の情報も取得できるようになっています。  

# カスタムリソース

SDK で取得する以外にも独自のリソース属性を付与することが可能です。  
公式で例示されている実行環境などは解りやすいですね。  
カスタムリソースを使う場合でも [命名規則](https://opentelemetry.io/docs/specs/semconv/resource/) に則ることが望ましいです。    

```bash
OTEL_RESOURCE_ATTRIBUTES=deployment.environment=development
OTEL_RESOURCE_ATTRIBUTES=deployment.environment=staging
OTEL_RESOURCE_ATTRIBUTES=deployment.environment=production
```

