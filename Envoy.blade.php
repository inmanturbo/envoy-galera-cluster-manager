@include('vendor/autoload.php');
@setup
    /**
    * https://github.com/vlucas/phpdotenv
     *  `composer require vlucas/phpdotenv`
     */

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    /**
     *  Password for "admin" user on all galera nodes
     *  current scripts require passwordless sudo
     */
    $adminPasswd=$_ENV['ADMIN_PASSWORD'];

    /**
     *  Password for remote mysqladmin user 
     *  
     */
    $mysqlAdminPasswd=$_ENV['MYSQL_ADMIN_PASSWORD'];

    /**
     *  Username for remote mysqladmin user 
     *  
     */
    $mysqlAdminUser=$_ENV['MYSQL_ADMIN_USERNAME'];

    /**
     *  Password for "admin" user on all galera nodes
     *  current scripts require passwordless sudo
     */
    $mysqlAdminPasswd=$_ENV['MYSQL_ADMIN_PASSWORD'];

    /**
     *  Password for root mysql account on all galera nodes
     */
    $mysqlRootPasswd=$_ENV['MYSQL_ROOT_PASSWORD'];

    /**
     *  Password for "admin" username on all galera nodes
     *  current scripts require passwordless sudo
     */
    $adminUsername=$_ENV['ADMIN_USERNAME'];

    /**
     *  MariaDb version to be used
     *  e.g 10.4
     */
     $mariaDbVersion=$_ENV['MARIADB_VERSION'];

     /**
     *  Yum/DNF releasever
     *  current scripts only support centos/rhel 8
     *  e.g rhel8-amd64
     */
     $releaseVer=$_ENV['RELEASE_VER'];

    /**
    * Define ssh logins for galera nodes
    * user@host.domain.tld
    */
    $db1=$_ENV['GALERA_NODE_ONE'];
    $db2=$_ENV['GALERA_NODE_TWO'];
    $db3=$_ENV['GALERA_NODE_THREE'];


    /**
    * Comma seperated list of hostnames
    * or ips for galera nodes
    */
    $galeraHostList=$_ENV['CLUSTER_HOST_LIST'];

    $galeraHostOne=explode(',',$galeraHostList)[0];
    $galeraHostTwo=explode(',',$galeraHostList)[1];
    $galeraHostThree=explode(',',$galeraHostList)[2];

    /**
    * Optional. Most critical database 
    * e.g. my_production_db_name
    */
    $primaryDb=$_ENV['PRIMARY_DB'];

    /**
    * Optional backup method to simple https file
    * server on network with curl support. 
    * e.g. fileserver.local
    */
    $simpleFileServerUri=$_ENV['SIMPLE_FILE_SERVER_URI'];

    /**
    * Optional Data repository for trtacking database changes
    * server on network with curl support. 
    * e.g. fileserver.local
    */
    $dataRepository=$_ENV['DATA_REPOSITORY'];

    /**
    * ssh login for cluster manager
    * or single node hypervisor
    * requires libvirt
    * uses virsh command
    * e.g. root@my_virt_host.local
    */
    $libvirtWorker=$_ENV['LIBVIRT_WORKER'];

    /**
     *  List all servers here
     *  All nodes' vm names in libvirt must be
     *  be same as their key in server array
     */
    $servers = [

        'db1'   => $db1,
        'db2'   => $db2,
        'db3'   => $db3,
        'libvirt' => $libvirtWorker,
        'local' => '127.0.0.1',
    ];

    /**
    * Which servers are meant to be
    * in the Galera Cluster
    */
    $galeraNodes = [
        'db1','db2','db3'
    ];

    /**
    * ssh connections for galera nodes
    */
    $galeraSSHConnections = [
        $db1,$db2,$db3
    ];

    /**
    * For timestamp versioning mysql dumps
    */
    $now = \Carbon\Carbon::now('America/New_York')->format('m_d_Y-h-i-s');

    $installDbCommand='echo "[mariadb]" |sudo tee /etc/yum.repos.d/mariadb.repo > /dev/null '.
                       '&& echo "name = MariaDB"|sudo tee -a /etc/yum.repos.d/mariadb.repo >/dev/null '. 
                       '&& echo "baseurl = http://yum.mariadb.org/'.$mariaDbVersion.'/'.$releaseVer.'"|sudo tee -a /etc/yum.repos.d/mariadb.repo > /dev/null '.
                       '&& echo "gpgkey=https://yum.mariadb.org/RPM-GPG-KEY-MariaDB"|sudo tee -a /etc/yum.repos.d/mariadb.repo > /dev/null '.
                       '&& echo "gpgcheck=1"|sudo tee -a /etc/yum.repos.d/mariadb.repo > /dev/null '.
                       '&& sudo dnf makecache '.
                       '&& sudo dnf install -y galera-4 '.
                       '&& sudo dnf install -y MariaDB-server MariaDB-client --disablerepo=AppStream';
    
    $nodeOneTemplate=<<<EOF
[mysqld]
binlog_format=ROW
default-storage-engine=innodb
innodb_autoinc_lock_mode=2
bind-address=0.0.0.0

# Galera Provider Configuration
wsrep_on=ON
wsrep_provider=/usr/lib64/galera-4/libgalera_smm.so

# Galera Cluster Configuration
wsrep_cluster_name="test_cluster"
wsrep_cluster_address="gcomm://$galeraHostList"

# Galera Synchronization Configuration
wsrep_sst_method=rsync

# Galera Node Configuration
wsrep_node_address="$galeraHostOne"
wsrep_node_name="db1"
EOF;

    $nodeTwoTemplate=<<<EOF
[mysqld]
binlog_format=ROW
default-storage-engine=innodb
innodb_autoinc_lock_mode=2
bind-address=0.0.0.0

# Galera Provider Configuration
wsrep_on=ON
wsrep_provider=/usr/lib64/galera-4/libgalera_smm.so

# Galera Cluster Configuration
wsrep_cluster_name="test_cluster"
wsrep_cluster_address="gcomm://$galeraHostList"

# Galera Synchronization Configuration
wsrep_sst_method=rsync

# Galera Node Configuration
wsrep_node_address="$galeraHostTwo"
wsrep_node_name="db3"
EOF;

    $nodeThreeTemplate=<<<EOF
[mysqld]
binlog_format=ROW
default-storage-engine=innodb
innodb_autoinc_lock_mode=2
bind-address=0.0.0.0

# Galera Provider Configuration
wsrep_on=ON
wsrep_provider=/usr/lib64/galera-4/libgalera_smm.so

# Galera Cluster Configuration
wsrep_cluster_name="test_cluster"
wsrep_cluster_address="gcomm://$galeraHostList"

# Galera Synchronization Configuration
wsrep_sst_method=rsync

# Galera Node Configuration
wsrep_node_address="$galeraHostThree"
wsrep_node_name="db3"
EOF;

$galeraHostConfigArray=[

    'db1' => ['template' => $nodeOneTemplate, 'hostname' => $galeraHostOne],
    'db2' => ['template' => $nodeTwoTemplate, 'hostname' => $galeraHostTwo],
    'db3' => ['template' => $nodeThreeTemplate, 'hostname' => $galeraHostThree],
];

$nogalera = $nogalera ?? "false";
$datadir = '/tmp/DATA_REPOSITORY_'.$now; 
@endsetup

@servers($servers)

@story('setup-cluster')

    install-keys
    configure-passwordless-sudo
    update-nodes
    install-mariadb-galera
    install-tools
    enable-mariadbs
    start-mariadbs
    set-root-mysql-passwd
    configure-mariadb-galera-node-one
    configure-mariadb-galera-node-two
    configure-mariadb-galera-node-three
    setup-firewall
    configure-selinux
    stop-mariadbs
    first-start1
    first-start-nodes
    create-enable-selinux-policies
    activate-selinux-policies
    secure-nodes
    log-task

@endstory

@story('up-cluster')

    start-nodes
    wait-for-cluster
    start1
    wait-for-one
    join-nodes
    enable-mariadbs
    log-task

@endstory

@story('down-cluster')

    stop-mariadbs
    disable-mariadbs
    shutdown-nodes
    log-task

@endstory

@story('start-cluster')

    start1
    wait-for-one
    join-nodes
    log-task

@endstory


{{-- EXAMPLE BACKUP --}}
{{-- 

    backs up data to git repo, will version control your data. Make sure your repository is private!!
    Also, you must have enough space in /tmp directory to stage data prior to push.
    Todo: add json and csv formatted dumps as well
    OPTIONS:
        --checkvalues=[false] true returns optional values and exits
        --repo=[REPOSITORY_URI] default: $dataRepository
        --nogalera=[false] true to run on standalone database server
        --hostname=[HOSTNAME] default: $galeraHostOne 
        --password=[PASSWORD] default: $mysqlAdminPasswd
        --user=[USERNAME] default: $mysqlAdminUser

--}}
@story('backup-db')

    check-values
    clone-data-repo
    prepare-data-repo-dirs
    desync
    data-dump-loop
    resync
    push-new-data
    cleanup-staged-data
    log-task

@endstory

{{-- 
    For some reason nodes must be resynced three times with big dump imports?    
--}}
@story('git-data-import')

    clone-data-repo
    desync
    load-database-from-git-repo
    resync
    cleanup-staged-data
    force-resync-nodes
    force-resync-nodes
    force-resync-nodes
    ping-nodes
    log-task

@endstory

@story('git-table-import')

    check-for-table
    clone-data-repo
    desync
    load-table-from-git-repo
    resync
    cleanup-staged-data
    force-resync-nodes
    force-resync-nodes
    force-resync-nodes
    ping-nodes
    log-task

@endstory

@task('install-keys',['on'=> ['local']])
    @foreach(array_values($servers) as $host)
        ssh-copy-id {{$host}}
    @endforeach
@endtask

@task('configure-passwordless-sudo',['on'=> $galeraNodes])
echo "{{$adminPasswd}}"|sudo -S echo "hello sudo" && echo '{{$adminUsername}} ALL=(ALL) NOPASSWD: ALL'|sudo tee -a /etc/sudoers
@endtask

@task('update-nodes',['on'=> $galeraNodes])
    sudo dnf update -y
@endtask

@task('install-mariadb-galera',['on'=> $galeraNodes])
    {{$installDbCommand}}
@endtask

@task('install-tools',['on'=> $galeraNodes])
    sudo dnf install -y rsync policycoreutils-python-utils jq
@endtask

{{-- SERVICES --}}
@task('enable-mariadbs',['on'=> $galeraNodes])
   sudo systemctl enable mariadb
@endtask

@task('disable-mariadbs',['on'=> $galeraNodes])
    sudo systemctl disable mariadb
@endtask

@task('start-mariadbs',['on'=> $galeraNodes])
   sudo systemctl start mariadb
@endtask

@task('stop-mariadbs',['on'=> ['db3','db2','db1']])
    sudo systemctl stop mariadb
@endtask

@task('enable-cockpit',['on'=> $galeraNodes])
   sudo systemctl enable --now cockpit.socket
@endtask

@task('set-root-mysql-passwd',['on'=> $galeraNodes])
    sudo mysqladmin --user=root password "{{$mysqlRootPasswd}}"
@endtask

@task('configure-mariadb-galera-node-one',['on'=> ['db1']])
    echo '{{$nodeOneTemplate}}'|sudo tee /etc/my.cnf.d/galera.cnf
@endtask

@task('configure-mariadb-galera-node-two',['on'=> ['db2']])
    echo '{{$nodeTwoTemplate}}'|sudo tee /etc/my.cnf.d/galera.cnf 
@endtask

@task('configure-mariadb-galera-node-three',['on'=> ['db3']])
    echo '{{$nodeThreeTemplate}}'|sudo tee /etc/my.cnf.d/galera.cnf 
@endtask

@task('setup-firewall',['on'=> $galeraNodes])
    sudo firewall-cmd --permanent --add-port=3306/tcp
    sudo firewall-cmd --permanent --add-port=4567/tcp
    sudo firewall-cmd --permanent --add-port=4568/tcp
    sudo firewall-cmd --permanent --add-port=4444/tcp
    sudo firewall-cmd --permanent --add-port=4567/udp
    sudo firewall-cmd --reload
@endtask

@task('configure-selinux',['on'=> $galeraNodes])
    #sudo semanage port -a -t mysqld_port_t -p tcp 4567
    sudo semanage port -a -t mysqld_port_t -p udp 4567
    sudo semanage port -a -t mysqld_port_t -p tcp 4568
    #sudo semanage port -a -t mysqld_port_t -p tcp 4444
    sudo semanage permissive -a mysqld_t
@endtask

@task('first-start1',['on' => ['db1']])
    sudo galera_new_cluster
    mysql -u root -p{{$mysqlRootPasswd}} -e "CREATE DATABASE selinux;CREATE TABLE selinux.selinux_policy (id INT NOT NULL AUTO_INCREMENT, PRIMARY KEY(id));INSERT INTO selinux.selinux_policy VALUES ();"
@endtask

@task('first-start-nodes',['on'=> ['db2','db3']])
    sudo systemctl start mariadb
@endtask

@task('create-enable-selinux-policies',['on'=> $galeraNodes])
    mysql -u root -p{{$mysqlRootPasswd}} -e 'INSERT INTO selinux.selinux_policy VALUES ();'
    sudo grep mysql /var/log/audit/audit.log | sudo audit2allow -M Galera
@endtask

@task('activate-selinux-policies',['on'=> $galeraNodes])
    sudo semodule -i Galera.pp
@endtask

@task('secure-nodes',['on'=> $galeraNodes])
    sudo semanage permissive -d mysqld_t
@endtask

@task('start-nodes',['on'=> ['libvirt']])
    @foreach($galeraNodes as $node)
        virsh start {{$node}}
    @endforeach
@endtask

@task('shutdown-nodes',['on'=> ['libvirt']])
    @foreach($galeraNodes as $node)
        virsh shutdown {{$node}}
    @endforeach
@endtask

@task('kill-nodes',['on'=> ['libvirt']])
    @foreach($galeraNodes as $node)
        virsh destroy {{$node}}
    @endforeach
@endtask

@task('reboot-nodes',['on'=> ['libvirt']])
    @foreach($galeraNodes as $node)
        virsh reboot {{$node}}
    @endforeach
@endtask

@task('list-nodes',['on'=> ['libvirt']])
        virsh list --all|grep db
@endtask

@task('wait-for-cluster', ['on' => ['local']])
    until ssh {{$db3}} "echo up"
        do
            echo "waiting for cluster"
        done
@endtask

@task('start1',['on' => ['db1']])
    sudo galera_new_cluster
    mysql -u root -p{{$mysqlRootPasswd}} -e "SHOW STATUS LIKE 'wsrep_cluster_size'"
@endtask

@task('wait-for-one', ['on' => ['local']])
    sleep 10
@endtask

@task('join-nodes',['on'=> ['db2','db3']])
    sudo systemctl start mariadb
    sleep 5 && mysql -u root -p{{$mysqlRootPasswd}} -e "SHOW STATUS LIKE 'wsrep_cluster_size'"
@endtask

@task('ping-nodes',['on'=> ['db1','db2','db3']])
    mysql -u root -p{{$mysqlRootPasswd}} -e "SHOW STATUS LIKE 'wsrep_cluster_size'"
    mysql -u root -p{{$mysqlRootPasswd}} -e "SHOW STATUS LIKE 'wsrep_local_state_comment'"
    mysql -u root -p{{$mysqlRootPasswd}} -e "SHOW STATUS LIKE 'wsrep_ready'"
    mysql -u root -p{{$mysqlRootPasswd}} -e "SHOW STATUS LIKE 'wsrep_connected'"
@endtask

@task('force-bootstrap',['on'=> ['db1']])
    sudo sed -i -e 's/safe_to_bootstrap: 0/safe_to_bootstrap: 1/' /var/lib/mysql/grastate.dat
@endtask

@task('check-values', ['on' => ['local']])
    @if($checkvalues)
        echo {{$nogalera}}
        echo {{$repo??$dataRepository}}
        echo {{$user??$mysqlAdminUser}}
        echo {{$hostname??$galeraHostOne}}
        echo {{$password??$mysqlAdminPasswd}}
        exit 1;
    @else
        echo "running tasks ..."
    @endif

@endtask

@task('clone-data-repo', ['on' => ['local']])
    git clone {{$repo??$dataRepository}} {{$datadir}}
    @if($checkout=="true")
        @if(!isset($hash))
            echo checkout option requires a hash. Please specifiy a commit hash.
            echo "cleaning up ..." && rm -rf {{$datadir}}
            exit 1;
        @endif
        cd {{$datadir}}
        git checkout {{$hash}}
    @endif
@endtask

@task('prepare-data-repo-dirs', ['on' => ['local']])
    mkdir -p {{$datadir}}/{mariadb,json,csv}
@endtask

@task('desync', ['on' => ['local']])
    @if($nogalera == "false")
        mysql -h {{$hostname??$galeraHostOne}} -u {{$user??$mysqlAdminUser}} -p{{$password??$mysqlAdminPasswd}} --execute "SET GLOBAL wsrep_desync = ON";
    @else
        echo "running for standalone server"
    @endif
@endtask

@task('data-dump-loop', ['on' => ['local']])
    for db in $(mysql -NBA -h {{$hostname??$galeraHostOne}} -u {{$user??$mysqlAdminUser}} -p{{$password??$mysqlAdminPasswd}} --execute "SHOW DATABASES";); do mkdir -p {{$datadir}}/${db}/{mariadb,json,csv}; mysqldump -h {{$hostname??$galeraHostOne}} -u {{$user??$mysqlAdminUser}} -p{{$password??$mysqlAdminPasswd}} --flush-logs --single-transaction ${db} |sudo tee {{$datadir}}/mariadb/${db}.sql > /dev/null; for table in $(mysql -NBA -h {{$hostname??$galeraHostOne}} -u {{$user??$mysqlAdminUser}} -p{{$password??$mysqlAdminPasswd}} ${db} --execute "SHOW TABLES";); do mysqldump -h {{$hostname??$galeraHostOne}} -u {{$user??$mysqlAdminUser}} -p{{$password??$mysqlAdminPasswd}} --flush-logs --single-transaction ${db} ${table} |sudo tee {{$datadir}}/${db}/mariadb/${table}.sql > /dev/null; done; done;
@endtask

@task('resync', ['on' => ['local']])
    @if($nogalera == "false")
        mysql -h {{$hostname??$galeraHostOne}} -u {{$user??$mysqlAdminUser}} -p{{$password??$mysqlAdminPasswd}} --execute "SET GLOBAL wsrep_desync = OFF";
    @else
        echo "ignoring galera options"
    @endif
@endtask

@task('push-new-data', ['on' => ['local']])
    cd {{$datadir}} 
    git add --all 
    git commit -m 'auto_committed on {{$now}}' 
    git push -u origin master;
@endtask

@task('cleanup-staged-data', ['on' => ['local']])
    rm -rf {{$datadir}}
@endtask

@task('force-resync-nodes', ['on' => ['db2', 'db3']])
    sudo rm -f /var/lib/mysql/grastate.dat
    sudo systemctl restart mariadb
@endtask

@task('load-database-from-git-repo', ['on' => ['local']])
    mysql -h {{$hostname??$galeraHostOne}} -u {{$user??$mysqlAdminUser}} -p{{$password??$mysqlAdminPasswd}} {{$db??$primaryDb}} < {{$datadir}}/mariadb/{{$newdb??$db??$primaryDb}}.sql;
@endtask

@task('load-table-from-git-repo', ['on' => ['local']])
    @if(!isset($table))
        echo "please enter a table"
        exit 1;
    @endif
    mysql -h {{$hostname??$galeraHostOne}} -u {{$user??$mysqlAdminUser}} -p{{$password??$mysqlAdminPasswd}} {{$db??$primaryDb}} < {{$datadir}}/{{$newdb??$db??$primaryDb}}/mariadb/{{$table}}.sql;
@endtask

@task('check-for-table', ['on' => ['local']])
    @if(!isset($table))
        echo "please enter a table"
        exit 1;
    @endif
@endtask

@task('log-task', ['on' =>['local']])
    echo "{{$logentry??'task'}} ran on {{$now}} "|tee -a ~/.envoy-task-log 
@endtask

@task('install-task', ['on' => ['local']])
    echo '#!/bin/bash'|tee ${PWD}/bin/{{$task}};
    echo "target_dir=${PWD}"|tee -a ${PWD}/bin/{{$task}};
    echo 'previous_dir=${PWD}'|tee -a ${PWD}/bin/{{$task}};
    echo 'cd $target_dir'|tee -a ${PWD}/bin/{{$task}};
    echo "${PWD}/vendor/bin/envoy run {{$task}}"|tee -a ${PWD}/bin/{{$task}};
    echo 'cd $previous_dir'|tee -a ${PWD}/bin/{{$task}};
@endtask