<?php
namespace App\Enum;

enum TechnologyType: string {
    // Runtime & App Servers
    case JAVA          = 'java';
    case TOMCAT        = 'tomcat';
    case WILDFLY       = 'wildfly';
    case JBOSS         = 'jboss';
    case WEBLOGIC      = 'weblogic';
    case WEBSPHERE     = 'websphere';
    case NODEJS        = 'nodejs';
    case PYTHON        = 'python';
    case PHP_FPM       = 'php_fpm';
    case DOTNET        = 'dotnet';
    case RUBY          = 'ruby';

    // Web Servers / Proxies
    case NGINX         = 'nginx';
    case APACHE        = 'apache';
    case HAPROXY       = 'haproxy';
    case TRAEFIK       = 'traefik';
    case CADDY         = 'caddy';

    // Databases
    case ORACLE        = 'oracle';
    case POSTGRESQL    = 'postgresql';
    case MYSQL         = 'mysql';
    case MARIADB       = 'mariadb';
    case MONGODB       = 'mongodb';
    case REDIS         = 'redis';
    case ELASTICSEARCH = 'elasticsearch';
    case CASSANDRA     = 'cassandra';
    case INFLUXDB      = 'influxdb';
    case MSSQL         = 'mssql';

    // Message Brokers
    case KAFKA         = 'kafka';
    case RABBITMQ      = 'rabbitmq';
    case ACTIVEMQ      = 'activemq';

    // Containers & Orchestration
    case DOCKER        = 'docker';
    case KUBERNETES    = 'kubernetes';
    case CONTAINERD    = 'containerd';

    // Monitoring & Observability
    case PROMETHEUS    = 'prometheus';
    case GRAFANA       = 'grafana';
    case JAEGER        = 'jaeger';

    // CI/CD
    case JENKINS       = 'jenkins';
    case GITLAB        = 'gitlab';

    // Autres
    case MEMCACHED     = 'memcached';
    case VAULT         = 'vault';

    public function getLabel(): string {
        return match($this) {
            self::TOMCAT        => 'Apache Tomcat',
            self::WILDFLY       => 'WildFly',
            self::JBOSS         => 'JBoss EAP',
            self::WEBLOGIC      => 'Oracle WebLogic',
            self::WEBSPHERE     => 'IBM WebSphere',
            self::NODEJS        => 'Node.js',
            self::PYTHON        => 'Python',
            self::PHP_FPM       => 'PHP-FPM',
            self::DOTNET        => '.NET',
            self::RUBY          => 'Ruby',
            self::NGINX         => 'Nginx',
            self::APACHE        => 'Apache HTTP',
            self::HAPROXY       => 'HAProxy',
            self::TRAEFIK       => 'Traefik',
            self::CADDY         => 'Caddy',
            self::ORACLE        => 'Oracle Database',
            self::POSTGRESQL    => 'PostgreSQL',
            self::MYSQL         => 'MySQL',
            self::MARIADB       => 'MariaDB',
            self::MONGODB       => 'MongoDB',
            self::REDIS         => 'Redis',
            self::ELASTICSEARCH => 'Elasticsearch',
            self::CASSANDRA     => 'Cassandra',
            self::INFLUXDB      => 'InfluxDB',
            self::MSSQL         => 'SQL Server',
            self::KAFKA         => 'Apache Kafka',
            self::RABBITMQ      => 'RabbitMQ',
            self::ACTIVEMQ      => 'ActiveMQ',
            self::DOCKER        => 'Docker',
            self::KUBERNETES    => 'Kubernetes',
            self::CONTAINERD    => 'containerd',
            self::PROMETHEUS    => 'Prometheus',
            self::GRAFANA       => 'Grafana',
            self::JAEGER        => 'Jaeger',
            self::JENKINS       => 'Jenkins',
            self::GITLAB        => 'GitLab',
            self::MEMCACHED     => 'Memcached',
            self::VAULT         => 'HashiCorp Vault',
            default             => ucfirst($this->value),
        };
    }

    public function getCategory(): string {
        return match($this) {
            self::TOMCAT, self::WILDFLY, self::JBOSS,
            self::WEBLOGIC, self::WEBSPHERE            => 'Serveur d\'application',
            self::JAVA, self::NODEJS, self::PYTHON,
            self::PHP_FPM, self::DOTNET, self::RUBY    => 'Runtime',
            self::NGINX, self::APACHE, self::HAPROXY,
            self::TRAEFIK, self::CADDY                 => 'Serveur web / Proxy',
            self::ORACLE, self::POSTGRESQL, self::MYSQL,
            self::MARIADB, self::MONGODB, self::REDIS,
            self::ELASTICSEARCH, self::CASSANDRA,
            self::INFLUXDB, self::MSSQL                => 'Base de données',
            self::KAFKA, self::RABBITMQ, self::ACTIVEMQ => 'Message Broker',
            self::DOCKER, self::KUBERNETES,
            self::CONTAINERD                            => 'Conteneurisation',
            self::PROMETHEUS, self::GRAFANA,
            self::JAEGER                                => 'Observabilité',
            self::JENKINS, self::GITLAB                => 'CI/CD',
            default                                    => 'Autre',
        };
    }

    public function getIcon(): string {
        return match($this) {
            self::TOMCAT, self::WILDFLY, self::JBOSS,
            self::WEBLOGIC, self::WEBSPHERE,
            self::JAVA                                 => 'ti-brand-java',
            self::NODEJS                               => 'ti-brand-nodejs',
            self::PYTHON                               => 'ti-brand-python',
            self::PHP_FPM                              => 'ti-brand-php',
            self::NGINX, self::APACHE, self::HAPROXY,
            self::TRAEFIK, self::CADDY                 => 'ti-server-2',
            self::ORACLE, self::POSTGRESQL, self::MYSQL,
            self::MARIADB, self::MSSQL                 => 'ti-database',
            self::MONGODB, self::CASSANDRA,
            self::ELASTICSEARCH, self::INFLUXDB        => 'ti-database-cog',
            self::REDIS, self::MEMCACHED               => 'ti-database-heart',
            self::KAFKA, self::RABBITMQ,
            self::ACTIVEMQ                             => 'ti-arrows-exchange',
            self::DOCKER                               => 'ti-brand-docker',
            self::KUBERNETES                           => 'ti-hexagon',
            self::PROMETHEUS, self::GRAFANA            => 'ti-chart-line',
            default                                    => 'ti-box',
        };
    }
}
