@include('vendor/autoload.php');
@setup
    /**
    * https://github.com/vlucas/phpdotenv
     *  `composer require vlucas/phpdotenv`
     */

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    /**
     *  Password for root user on all galera nodes
     */
    $passwd=$_ENV['GALERA_PASS'];
    $db1=$_ENV['GALERA_NODE_ONE'];
    $db2=$_ENV['GALERA_NODE_TWO'];
    $db3=$_ENV['GALERA_NODE_THREE'];
    $libvirtWorker=$_ENV['LIBVIRT_WORKER'];

    /**
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
    
    $dbNodes = [
        'db1','db2','db3'
    ];

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
    dnf install -y rsync policycoreutils-python-utils
@endtask

@task('update-dbs',['on'=> $dbNodes])
    dnf update -y
@endtask

@task('enable-dbs',['on'=> $dbNodes])
    systemctl enable mariadb
@endtask


@task('enable-cockpit',['on'=> $dbNodes])
    systemctl enable --now cockpit.socket
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
systemctl stop mariadb
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
    systemctl start mariadb
    sleep 5 && mysql -u root -p{{$passwd}} -e "SHOW STATUS LIKE 'wsrep_cluster_size'"
@endtask


@story('down-cluster')
    stop-nodes
    disable-dbs
    shutdown-dbs
@endstory

@task('disable-dbs',['on'=> $dbNodes])
    systemctl disable mariadb
@endtask

@task('shutdown-dbs',['on'=> ['libvirt']])
    @foreach($dbNodes as $node)
        virsh destroy {{$node}}
    @endforeach
@endtask

@task('stop-nodes',['on'=> ['db3','db2','db1']])
    systemctl stop mariadb
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
until ssh root@db3.qwlocal "echo up"
do
echo "waiting for cluster"
done
@endtask

@task('wait-for-one', ['on' => ['local']])
sleep 10
@endtask

@task('stop-nodes',['on'=> ['db3','db2','db1']])
systemctl stop mariadb
@endtask