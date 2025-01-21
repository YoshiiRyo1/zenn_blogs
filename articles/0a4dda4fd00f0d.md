---
title: "Kubernetesを1から勉強したくてminikubeをインストールしてみた"
emoji: "🐳"
type: "tech" # tech: 技術記事 / idea: アイデア
topics: ["Kubernetes","minikube"]
published: true
---
こんにちは。  
ご機嫌いかがでしょうか。  
"No human labor is no human error" が大好きな[吉井 亮](https://twitter.com/YoshiiRyo1)です。  

Kubernetes をそろそろ勉強しようかなという気持ちになりました。  
ECS をこよなく愛している・・・わけではないのですが、ECS はお手軽なので AWS 上でコンテナ動かすときは ECS を使っていました。  

さて、どうしようかなと調べていたところ、[minikube](https://minikube.sigs.k8s.io/docs/) の存在を知りました。これは PC 上で Kubernetes クラスターを構築できる優れたツールです。
これを PC にインストールして、Kubernetes を勉強してみようと思います。  

## minikube のインストール

インストール前に前提条件を確認しておきます。[minikube start](https://minikube.sigs.k8s.io/docs/start/) から引用です。  

- 2 CPUs or more
- 2GB of free memory
- 20GB of free disk space
- Internet connection
- Container or virtual machine manager, such as: Docker, QEMU, Hyperkit, Hyper-V, KVM, Parallels, Podman, VirtualBox, or VMware Fusion/Workstation

[minikube start](https://minikube.sigs.k8s.io/docs/start/) に記載の通り進めていけば大丈夫です。難しくないです。私は Mac なので以下です。  

```bash
$ brew install minikube
```

引数の `start` を付けて実行です。正常にスタートしたかどうかは `status` で確認できます。  

```bash
$ minikube start

$ minikube status
minikube
type: Control Plane
host: Running
kubelet: Running
apiserver: Running
kubeconfig: Configured
```

コマンドだけではなく GUI で見たいよという方は [minikube dashboard](https://minikube.sigs.k8s.io/docs/commands/dashboard/) でダッシュボードを表示できます。  
以下を実行するとブラウザが立ち上がり、ダッシュボードが表示されます。  

```bash
$ minikube dashboard
```

## kubectl のインストール

クラスターを管理するために kubectl をインストールします。  
[Install Tools](https://kubernetes.io/docs/tasks/tools/) に記載通りです。  

```bash
$ brew install kubectl
```

bash-completion と alias も設定しておきます。  

```bash
$ brew install bash-completion@2
$ kubectl completion bash >$(brew --prefix)/etc/bash_completion.d/kubectl
```

`~/.bash_profile` に以下を追記しシェルをリロードします。  

```text
brew_etc="$(brew --prefix)/etc" && [[ -r "${brew_etc}/profile.d/bash_completion.sh" ]] && . "${brew_etc}/profile.d/bash_completion.sh"
source <(kubectl completion bash)
alias k=kubectl
complete -o default -F __start_kubectl k
```

## アプリケーションのデプロイ

クラスターの準備ができました。続いてアプリケーションをデプロイしてみます。  
nginx だけをデプロイする簡単な例です。minikube の Get Started にはコマンドでデプロイする方法が記述されていますが、yaml 記法を勉強したかったので yaml で書きます。  
`nginx-deployment.yaml` と `nginx-service.yaml` を作成します。  

```yaml:nginx-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: nginx
spec:
  replicas: 1
  selector:
    matchLabels:
      app: nginx
  template:
    metadata:
      labels:
        app: nginx
    spec:
      containers:
      - name: nginx-container
        image: nginx
        imagePullPolicy: IfNotPresent
```

```yaml:nginx-service.yaml
apiVersion: v1
kind: Service
metadata:
  name: nginx-service
spec:
  selector:
    app: nginx
  ports:
    - protocol: TCP
      port: 8080
      targetPort: 80
```

`k create` でデプロイです。  

```bash
$ k create -f . --save-config
deployment.apps/nginx created
service/nginx-service created
```

無事作成されたようです。`k get` で確認します。  

```bash
$ k get pod,deploy
NAME                         READY   STATUS    RESTARTS   AGE
pod/nginx-58b75f9cbf-592zm   1/1     Running   0          6m55s

NAME                    READY   UP-TO-DATE   AVAILABLE   AGE
deployment.apps/nginx   1/1     1            1           6m55s
```

## アプリケーションアクセス

デプロイした nginx にアクセスしてみます。  
Kubernetes には様々な方法がありますが、今回は Ingress を使います。  

`nginx-ingress.yaml` を作成します。  

```yaml:nginx-ingress.yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: example-ingress
spec:
  rules:
    - http:
        paths:
          - pathType: Prefix
            path: /
            backend:
              service:
                name: nginx-service
                port:
                  number: 8080
```

`k create` で Ingress を作成します。  

```bash
$ minikube addons enable ingress

$ k create -f nginx-ingress.yaml 
ingress.networking.k8s.io/example-ingress created

$ k get ingress
NAME              CLASS   HOSTS   ADDRESS   PORTS   AGE
example-ingress   nginx   *                 80      5s
```

Ingress は問題なさそうです。  

minikube でトンネルを張ってあげると nginx にアクセスできるようになります。  

```bash
$ minikube tunnel
✅  トンネルが無事開始しました

📌  注意: トンネルにアクセスするにはこのプロセスが存続しなければならないため、このターミナルはクローズしないでください ...

❗  example-ingress service/ingress は次の公開用特権ポートを要求します:  [80 443]
🔑  sudo permission will be asked for it.
🏃  example-ingress サービス用のトンネルを起動しています。
Password:  # PCのログインパスワードを入力
```

パスワード入力後に何もエラーが出なればトンネルが張られています。  

別のターミナルをひらいて curl でアクセスすると Welcome nginx が表示されるはずです。  

```bash
$ curl http://localhost/
〜〜省略〜〜
<h1>Welcome to nginx!</h1>
〜〜省略〜〜
```

トンネルは `ctrl + c` で止められます。  

## Docker イメージの利用

nginx は Docker Hub にあるイメージを利用しましたが、自分で作ったイメージを利用することもできます。  
minikube 内で Docker イメージを利用する方法は複数存在します。  
[Pushing images](https://minikube.sigs.k8s.io/docs/handbook/pushing/)

今回は Docker を使います。  
ビルドするための Dockerfile を作成します。  

```Dockerfile
FROM nginx

COPY index.html /usr/share/nginx/html/index.html
```

index.html も作成します。  

```html:index.html
<head>
<body>Hello host</body>
</head>
```

ビルドする前に確認しておく設定があります。前述した `nginx-deployment.yaml` の `imagePullPolicy` が **IfNotPresent** または **Never** になっていることを確認します。(デフォルトは Always です)  

環境変数を minikube へ渡した後にビルドします。  
docker-env を渡してあげるのがポイントです。書くまでもないですが、これが有効なのはコマンドを実行したターミナルのみです。また、minikube を再起動した場合は再度コマンドを実行する必要があります。  

```bash
eval $(minikube docker-env)
docker build -t my-nginx .
```

ビルドした後は `nginx-deployment.yaml` の `image` 行を変更し、`k apply` でデプロイします。  

```yaml:nginx-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: nginx
spec:
  replicas: 1
  selector:
    matchLabels:
      app: nginx
  template:
    metadata:
      labels:
        app: nginx
    spec:
      containers:
      - name: nginx-container
        image: my-nginx   # ここを変更
        imagePullPolicy: IfNotPresent
```

```bash
$ k diff -f nginx-deployment.yaml
$ k apply -f nginx-deployment.yaml
```

## 永続ストレージの利用

永続ストレージが伝いたくなる場面がそのうちあると思い、永続ストレージの使い方も調べてみました。  

minikube では hostPath による永続ストレージをサポートしています。  
以下の例のように、`hostPath` に `path` を指定することで永続ストレージを利用できます。  

```yaml
apiVersion: v1
kind: Pod
metadata:
  name: test-local-path
spec:
  restartPolicy: OnFailure
  containers:
    - name: busybox
      image: busybox:stable
      command: ["sh", "-c", "echo 'local-path-provisioner' > /test/file1"]
      volumeMounts:
      - name: data
        mountPath: /test
  volumes:
    - name: data
      hostPath:
        path: /data/pv0001
        type: DirectoryOrCreate
```

どこの path を指定しても良いというわけではく、決まった場所に置く必要があります。  
[Persistent Volumes](https://minikube.sigs.k8s.io/docs/handbook/persistent_volumes/) から引用します。  

- /data*
- /var/lib/minikube
- /var/lib/docker
- /var/lib/containerd
- /var/lib/buildkit
- /var/lib/containers
- /tmp/hostpath_pv*
- /tmp/hostpath-provisioner*

* mount point for another directory, stored under /var or on a separate data disk

これらのディレクトリは PC ではなく、minikube のノードにあるディレクトリです。  
永続ストレージ内のファイルを読み書きするにはノードに ssh でログインする必要があります。  

```bash
$ minikube ssh -n minikube  ls /data/pv0001
file1
```

## まとめ

EKS や GKE を使って勉強を始めるのも良いですが、最初の一歩としての minikube はありだと思いました。  
Kubernetes の基礎、yaml 記法、API の種類などを理解するには十分です。

## 参考

[minikube](https://minikube.sigs.k8s.io/docs/)  
[Kubernetes Documentation](https://kubernetes.io/docs/home/)  
[kubectlチートシート](https://kubernetes.io/ja/docs/reference/kubectl/cheatsheet/)  
