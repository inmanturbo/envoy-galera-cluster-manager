@include('vendor/autoload.php');
@setup
    /**
    * https://github.com/vlucas/phpdotenv
     *  `composer require vlucas/phpdotenv`
     */

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    /**
     *  Password for "ansible" user on all galera nodes
     */
    $passwd=$_ENV['GALERA_PASS'];
    $db1=$_ENV['GALERA_NODE_ONE'];
    $db2=$_ENV['GALERA_NODE_TWO'];
    $db3=$_ENV['GALERA_NODE_THREE'];
    $primaryDb=$_ENV['PRIMARY_DB'];
    $simpleFileServerUrl=$_ENV['SIMPLE_FILE_SERVER_URL'];
    $libvirtWorker=$_ENV['LIBVIRT_WORKER'];

    /**
     *  All nodes' vm names in libvirt must be
     *  be same as their key in server array
     */
    $servers = [
        'db1'   => $db1,
        'db2'   => $db2,
        'db3'   => $db3,
        'virthost' => 'root@virthost.qwlocal',
        'xps' => 'root@centos-xps.turbodomain',
        'dc' => 'dc.qwlocal',
        'libvirt' => $libvirtWorker,
        'local' => '127.0.0.1',
    ];
    
    $dbNodes = [
        'db1','db2','db3'
    ];

    $now = \Carbon\Carbon::now()->format('m_d_Y-h');

    $installDbCommand='echo "[mariadb]" > /etc/yum.repos.d/mariadb.repo && echo "name = MariaDB" >> /etc/yum.repos.d/mariadb.repo && echo "baseurl = http://yum.mariadb.org/10.4/rhel8-amd64" >> /etc/yum.repos.d/mariadb.repo && echo "gpgkey=https://yum.mariadb.org/RPM-GPG-KEY-MariaDB" >> /etc/yum.repos.d/mariadb.repo && echo "gpgcheck=1" >> /etc/yum.repos.d/mariadb.repo && dnf makecache && dnf install -y galera-4 && dnf install -y MariaDB-server MariaDB-client --disablerepo=AppStream'
@endsetup

@servers($servers)

@task('install-keys',['on'=> ['local']])
    @foreach($dbNodes as $node)
        ssh-copy-id {{$node}}
    @endforeach
@endtask

@task('start-dbs',['on'=> ['libvirt']])
    @foreach($dbNodes as $node)
        virsh start {{$node}}
    @endforeach
@endtask


@task('reboot-dbs',['on'=> ['libvirt']])
@foreach($dbNodes as $node)
    virsh reboot {{$node}}
@endforeach
@endtask

@task('install-dbs',['on'=> $dbNodes])
    {{$installDbCommand}}
@endtask

@task('install-tools',['on'=> $dbNodes])
    sudo dnf install -y rsync policycoreutils-python-utils jq
@endtask

@task('update-dbs',['on'=> $dbNodes])
    sudo dnf update -y
@endtask

@task('enable-dbs',['on'=> $dbNodes])
   sudo systemctl enable mariadb
@endtask


@task('enable-cockpit',['on'=> $dbNodes])
   sudo systemctl enable --now cockpit.socket
@endtask

@task('setup-firewall',['on'=> $dbNodes])
    sudo firewall-cmd --permanent --add-port=3306/tcp
    sudo firewall-cmd --permanent --add-port=4567/tcp
    sudo firewall-cmd --permanent --add-port=4568/tcp
    sudo firewall-cmd --permanent --add-port=4444/tcp
    sudo firewall-cmd --permanent --add-port=4567/udp
    sudo firewall-cmd --reload
@endtask

@task('selinux-conf',['on'=> $dbNodes])
    #sudo semanage port -a -t mysqld_port_t -p tcp 4567
    sudo semanage port -a -t mysqld_port_t -p udp 4567
    sudo semanage port -a -t mysqld_port_t -p tcp 4568
    #sudo semanage port -a -t mysqld_port_t -p tcp 4444
    sudo semanage permissive -a mysqld_t
@endtask

@task('stop-dbs',['on'=> $dbNodes])
    sudo systemctl stop mariadb
@endtask


@task('policies',['on'=> $dbNodes])
    mysql -u root -p{{$passwd}} -e 'INSERT INTO selinux.selinux_policy VALUES ();'
    sudo grep mysql /var/log/audit/audit.log | sudo audit2allow -M Galera
@endtask

@task('activate-policies',['on'=> $dbNodes])
    sudo semodule -i Galera.pp
@endtask

@task('secure-nodes',['on'=> $dbNodes])
    sudo semanage permissive -d mysqld_t
@endtask


@story('start-cluster')
    start1
    start-nodes
@endstory

@task('start1',['on' => ['db1']])
    sudo galera_new_cluster
    mysql -u root -p{{$passwd}} -e "SHOW STATUS LIKE 'wsrep_cluster_size'"
@endtask


@task('start-nodes',['on'=> ['db2','db3']])
    sudo systemctl start mariadb
    sleep 5 && mysql -u root -p{{$passwd}} -e "SHOW STATUS LIKE 'wsrep_cluster_size'"
@endtask

@task('ping-nodes',['on'=> ['db1','db2','db3']])
    mysql -u root -p{{$passwd}} -e "SHOW STATUS LIKE 'wsrep_cluster_size'"
@endtask


@story('down-cluster')
    stop-nodes
    disable-dbs
    shutdown-dbs
@endstory

@task('disable-dbs',['on'=> $dbNodes])
    sudo systemctl disable mariadb
@endtask

@task('shutdown-dbs',['on'=> ['libvirt']])
    @foreach($dbNodes as $node)
        virsh shutdown {{$node}}
    @endforeach
@endtask

@task('stop-nodes',['on'=> ['db3','db2','db1']])
    sudo systemctl stop mariadb
@endtask

@story('up-cluster')
    start-dbs
    wait-for-cluster
    start1
    wait-for-one
    start-nodes
    enable-dbs
@endstory

@task('wait-for-cluster', ['on' => ['local']])
    until ssh {{$db3}} "echo up"
        do
            echo "waiting for cluster"
        done
@endtask

@task('wait-for-one', ['on' => ['local']])
    sleep 10
@endtask

@task('stop-nodes',['on'=> ['db3','db2','db1']])
    sudo systemctl stop mariadb
@endtask

@task('force-bootstrap',['on'=> ['db1']])
    sudo sed -i -e 's/safe_to_bootstrap: 0/safe_to_bootstrap: 1/' /var/lib/mysql/grastate.dat
@endtask

@task('backup-db',['on'=> ['db1']])
    mysql -u root -p{{$passwd}} --execute "SET GLOBAL wsrep_desync = ON";
    sudo mysqldump -u root -p{{$passwd}} --flush-logs --databases {{$primaryDb}} |sudo tee /backups/{{$primaryDb}}-{{$now}}.sql > /dev/null;
    sudo mysqldump -u root -p{{$passwd}} --flush-logs --all-databases |sudo tee /backups/db-backup-all-{{$now}}.sql > /dev/null;
    mysql -u root -p{{$passwd}} --execute "SET GLOBAL wsrep_desync = OFF";
    curl -sF file=@/backups/{{$primaryDb}}-{{$now}}.sql {{$simpleFileServerUrl}}  > ~/.backup-report-{{$primaryDb}}-{{$now}}.log;
    curl -sF file=@/backups/db-backup-all-{{$now}}.sql {{$simpleFileServerUrl}} > ~/.backup-report-all-{{$now}}.log;
    if [[ $(jq '.success' ~/.backup-report-{{$primaryDb}}-{{$now}}.log) = "true" ]]; then sudo rm /backups/{{$primaryDb}}-{{$now}}.sql;fi;
    if [[ $(jq '.success' ~/.backup-report-all-{{$now}}.log) = "true" ]]; then sudo rm /backups/db-backup-all-{{$now}}.sql;fi;
@endtask

@task('install-script', ['on' => ['local']])
    echo '#!/bin/bash'|tee ${PWD}/bin/{{$task}};
    echo "target_dir=${PWD}"|tee -a ${PWD}/bin/{{$task}};
    echo 'previous_dir=${PWD}'|tee -a ${PWD}/bin/{{$task}};
    echo 'cd $target_dir'|tee -a ${PWD}/bin/{{$task}};
    echo "${PWD}/vendor/bin/envoy run {{$task}}"|tee -a ${PWD}/bin/{{$task}};
    echo 'cd $previous_dir'|tee -a ${PWD}/bin/{{$task}};
@endtask

@task('list-nodes',['on'=> ['libvirt']])
        virsh list --all|grep db
@endtask
