---
title: "AWS パブリックIPアドレス 料金体系変更に対応する作業 EC2編"
emoji: "💰"
type: "tech" # tech: 技術記事 / idea: アイデア
topics: ["AWS","EC2","VPC"]
published: true
---

こんにちは。  
ご機嫌いかがでしょうか。  
"No human labor is no human error" が大好きな[吉井 亮](https://twitter.com/YoshiiRyo1)です。  

ちょっとした衝撃を受ける発表がアメリカ時間の2023年7月28日に AWS がありました。  
2024年2月1日よりパブリック IP アドレスに時間あたりの課金が発生するようになります。  
従来は使っているインスタンスに紐付いたパブリック IP アドレスには課金が無く、使っていないものだけの課金でしたが、2024年2月1日以降は使っているかどうかに関わらず課金されます。  
課金は 0.005USD/Hour になっています。  
0.005USD/Hour * 24h * (28|29|30|31)days * インスタンス数 の計算式で増加分が計算できます。  

@[card](https://aws.amazon.com/jp/blogs/aws/new-aws-public-ipv4-address-charge-public-ip-insights/)  
@[card](https://aws.amazon.com/jp/blogs/news/identify-and-optimize-public-ipv4-address-usage-on-aws/)  

## 対応作業

月曜日の朝に出社してアナウンスを見てからやることを考えました。  
やる前からインパクトが大きくなると予想していました。  

- 現在使用しているパブリック IP アドレス数を割り出し増えるコストを計算する
- 可能な限り EC2 インスタンスからパブリック IP アドレスを外す
- インターネットアクセスが (EC2 からアウト) 必要なものは NAT Gateway を用意する
- EC2 で直接公開している Web サービスを ALB に集約する
- IPv6 アーキテクチャを検討する

### 現在使用しているパブリック IP アドレス数を割り出し増えるコストを計算する

[パブリック IP インサイト](https://docs.aws.amazon.com/vpc/latest/ipam/view-public-ip-insights.html) という機能が同時にリリースされました。  
マネジメントコンソールからこれを開くをリージョンで使用しているパブリック IP アドレスを表示してくれます。  

Jeff Bar さんのブログから画像を拝借します。EIP、EC2 自動割り当て、マネージドサービス、BYOIP ごとに利用数を表示してくれます。  
![img](https://d2908q01vomqb2.cloudfront.net/da4b9237bacccdf19c0760cab7aec4a8359010b0/2023/07/19/pip_top_1.png)  

インサイトだと NAT Gateway や ELB などが含まれているため、本エントリでメインに考えている EC2 のパブリック IP アドレス数は判りません。  
マネジメントコンソールで数えられるくらいならそれでもいいのですが、今回は1000以上インスタンスが存在するので AWS CLI でパブリック IP アドレス付きインスタンスを数えてみます。  

```bash
# ファイル出力は必須ではありません。いろいろと加工したかったので一旦ファイルへ出力しています
$ aws ec2 describe-instances > describe-instances.out
$ cat describe-instances.out | jq -r '.Reservations[].Instances[] | select(.PublicIpAddress != null) | .InstanceId' | wc -l
```

上のコマンドでパブリック IP アドレスが付いているインスタンス数が算出できました。  
私が関与しているシステムでは約900でした。計算してみると 0.005USD/Hour * 24h * 31days * 900 = 3348USD/Month になり笑っていられない増額です。  

### 可能な限り EC2 インスタンスからパブリック IP アドレスを外す

EC2 インスタンスからパブリック IP アドレスを外したいと思います。外せる条件を考えてみました。  

- インターネットに直接公開しているポートが無い
    - 直接公開しているポートがある場合は、ELB や CloudFront の背後に置くことを検討する
    - ↑ 金額的な理由より DDoS 対策や WAF などセキュリティ上の理由が大きいかも
- インターネットからの受信は無いが、送信が必要な場合は NAT Gateway など Internet Gateway を介さないインターネットへのルートを設定する

条件はシステムによって異なると思います。  
条件よりももっと難しい問題があります。それは EC2 インスタンス作成時にパブリック IP アドレスの自動割り当てを有効にしていると後から変更できないという点です。
今現在パブリック IP アドレスの自動割り当てをしているインスタンスは AMI を取得して作り直ししかなさそうです。  
ENI を複数利用しているパブリック IP アドレスが割り当てられないという仕様を逆手に取った手段がありそうですが、本来的なやり方ではないので採用したくありません。  

AMI によるインスタンスの入れ替えは事故を起こす可能性があります。慎重に作業を進めるしかありません。  
インスタンス ID やプライベート IP アドレス、タグなど変更されてしまうもの、手動で再設定が必要なものを十分なテストで洗い出します。  
IaC で管理済みなら少し安心できます。動的に変わる箇所以外はコントール可能だからです。IaC をこれから導入するなら既存リソースをインポートしてコントール下に置きます。  
IaC が無理なら AWS CLI の describe-instances で既存設定値を取得して run-instances のオプションに渡すことを半自動化は可能です。新旧インスタンスで describe-instances の結果を diff かけることも有効です。  

**2023年8月1日追記**
AWS Backup から EC2 をリストアするとほとんど元の状態に戻せます。  
タグも2023年5月22日以降に作成されたバックアップであれば事前設定をしておくことで復元できます。AMI によるインスタンス入れ替えの手段になると思います。    
[AWS Backup でEC2の設定がどこまで戻るか試してみた](https://zenn.dev/ryoyoshii/articles/c573e96012957b) 

ALB 背後にあるインスタンスなら、ターゲットグループを2つ作り重みを付けながら少しずつトラフィックを移行していく方法を検討します。  
AutoScaling な EC2 なら、起動テンプレートを変更するだけで済むかもしれません。  

#### サブネット自動割り当てのオフ

従来までは使用しているプライマリーなパブリック IP アドレスは課金対象外だったのであまり気にしていなかったのですが、これからはサブネットのパブリック IP アドレス自動割り当てはオフにしたいと思います。  
何も考えずにインスタンス起動をするとパブリック IP アドレスが付与される事態を防ぎます。パブリック IP アドレスが必要なインスタンスは立ち上げ時に明示的に指定するような運用になります。  
![img](https://d2908q01vomqb2.cloudfront.net/b3f0c7f6bb763af1be91d9e74eabfeb199dc1f1f/2023/07/29/ip-insights-blog-figure-5-new-1-1024x440.png)  


パブリック IP アドレス自動割り当てが有効になっているサブネットは以下のコマンドで表示します。  
```bash
aws ec2 describe-subnets | jq -r '.Subnets[] | select(.MapPublicIpOnLaunch == true ) | .SubnetId' 
```

上で表示された`サブネットID`を指定してコマンドで無効にします。  
```bash
$ aws ec2 modify-subnet-attribute --no-map-public-ip-on-launch --subnet-id サブネットID
```

### インターネットアクセスが (EC2 からアウト) 必要なものは NAT Gateway を用意する

インターネットアクセス用に NAT Gateway を用意します。  
ただ、料金が気になるところです。執筆日時点での東京リージョンの [Amazon VPC の料金](https://aws.amazon.com/jp/vpc/pricing/) を見てみます。  

- NAT ゲートウェイあたりの料金 (USD/時) : 0.062USD
- 処理データ 1 GB あたりの料金 (USD) : 0.062USD
- Amazon EC2 からインターネットへのデータ転送 (アウト) : EC2 データ通信料金と同等

NAT Gateway 料金とパブリック IP アドレス新料金を比較して、NAT Gateway のほうが安ければ採用したいと考えます。  
処理データ量は事前に見積もることが難しいですが、先月の AWS 請求を見て Data Transfer の項目からある程度予測を立てるしかありません。  

時間料金 : 0.062USD/Hour * 24h * 31days * {NAT Gateway台数}  
通信料金 : 0.062 * 予測データ量  

「パブリック IP アドレスによって発生する新料金 > 時間料金 ＋ 通信料金」 ← この式が成立てば OK です。  

### EC2 で直接公開している Web サービスを ALB に集約する

ALB のホストベースルーティングを使うことで複数のホストを1つの ALB に集約することが可能です。  
EC2 を直接外部公開しているケースではこのホストベースルーティングが使えるかもしれません。ルールは ALB あたり100まで、証明書は25までというデフォルト制限になっているのでそれくらいのサイト数なら検討の余地はあります。  
ただ、注意しなければならないのは、障害発生時の爆発半径が大きくなるということです。1つの ALB がサービスダウンやスローダウンした際に影響を受けるサイトが増えてしまいます。  

### IPv6 アーキテクチャを検討する

今すぐではありません。近い将来 IPv6 が世に普及してくる時が来るはずです。その時に備えておくのは悪いことではありません。  

AWS が IPv6 をサポートしているサービスは以下に記述があります。内部的なネットワークであれば IPv6 で組めそうですが、外部公開サービスはまだ厳しいかもしれません。そもそもクライアント側 (ホームルーターや ISP など) もまだ IPv6 サポートしてない現状もあると思います。  
[AWS services that support IPv6](https://docs.aws.amazon.com/vpc/latest/userguide/aws-ipv6-support.html)  

AWS のブログに IPv6 アーキテクチャ構成例が多く紹介されています。ここの紹介されている構成をベースにカスタマイズしていくことが近道だと感じます。  

[Dual-stack IPv6 architectures for AWS and hybrid networks](https://aws.amazon.com/jp/blogs/networking-and-content-delivery/dual-stack-ipv6-architectures-for-aws-and-hybrid-networks/)  
[Dual-stack IPv6 architectures for AWS and hybrid networks – Part 2](https://aws.amazon.com/jp/blogs/networking-and-content-delivery/dual-stack-architectures-for-aws-and-hybrid-networks-part-2/)  

## まとめ

今回アナウンスの新料金はかなり驚きました。AWS の実質値上げ自体が珍しいことですし、そこか！といった感情もありました。  
IPv4 アドレス枯渇はかなり前から言われていましたが、パブリッククラウドの普及により現実味を増してきたのでしょうか。  
EC2 バリバリ使っているアカウントだと影響は大きいですし、2024年2月までとなると作業期間も十分に取れない可能性はあると思います。少しずつやるしかなさそうです。  


## 参考

[describe-instances](https://awscli.amazonaws.com/v2/documentation/api/latest/reference/ec2/describe-instances.html)  
[[小ネタ]AWS BackupでEC2をリストアした後に変更される項目や再設定が必要な項目](https://dev.classmethod.jp/articles/aws_backup_genmanage/)  
[[アップデート]AWS Backupはタグと一緒にリソースをリストアすることを対応しました](https://dev.classmethod.jp/articles/restoring-resources-with-tags-using-aws-backup/)  
[AWS services that support IPv6](https://docs.aws.amazon.com/vpc/latest/userguide/aws-ipv6-support.html)  

