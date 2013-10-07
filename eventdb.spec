#
# spec file for package eventdb
#
# Initial revision from
# https://build.opensuse.org/package/show?package=eventdb&project=server%3Amonitoring 
#
# Revised and updated by Michael Friedrich
# (c) 2012-2013 Netways GmbH
#
# This file and all modifications and additions to the pristine
# package are under the same license as the package itsels

Name:           eventdb
Summary:        Manage and administrate recipient events for Icinga and Nagios
Version:        2.0.6
Release:        1%{?dist}%{?custom}
Url:            https://www.netways.org/projects/show/eventdb
License:        GPL v2 or later
AutoReqProv:	no
Group:          Applications/System
Source0:        eventdb-%version.tar.gz
Requires:       mysql
%if "%{_vendor}" == "suse"
BuildRequires:  apache2
BuildRequires:  php5
Recommends:     snmptt
%if 0%{?suse_version} > 1020
BuildRequires:  fdupes
%endif
%endif
%if "%{_vendor}" == "redhat"
BuildRequires:	httpd
%if 0%{?el5} 
BuildRequires:  php53
%else
BuildRequires: 	php >= 5.2
%endif
%endif
BuildArch:      noarch
BuildRoot:      %{_tmppath}/%{name}-%{version}-build
%define         nsusr icinga
%define         nsgrp icinga
%define         libexecdir /usr/lib/nagios/plugins
%define         webdir %{_datadir}/%{name}

%if "%{_vendor}" == "redhat"
%define         apacheuser apache
%define         apachegroup apache
%define         webserver httpd
%endif
%if "%{_vendor}" == "suse"
%define         apacheuser wwwrun
%define         apachegroup www
%define         webserver apache2
%endif
%define         icingawebdir /usr/share/icinga-web
%define         clearcache %{_bindir}/icinga-web-clearcache
%define         docdir %{_datadir}/doc/%{name}
%define         apache2_sysconfdir %{_sysconfdir}/%{webserver}/conf.d


%description
EventDB is a Tool for an easy handling of event based data, e.g. SNMP Traps,
eMails or logfiles in monitoring systems like Icinga/Nagios. It has multiple plugins for different data sources and can be extended for new data input channels. The Classic webinterface allows users to search, filter and acknowledge all events. For Icinga Web, there's a seperated cronk integration available.
Integration in Icinga/Nagios is made through a Icinga/Nagios plugin, which is also part of this package.


%package www
Summary:        Classic Web interface
Group:          Applications/System
Requires:       %{name} = %{version}
Requires:	%{webserver}
Requires:       php-mysql
Requires:       php-mbstring

%description www
This package contains all parts for the classic web interface of eventdb.


%package syslog-ng2mysql
Summary:        Daemon for writing directly into the MySQL database 
Group:          Applications/System
%if "%{_vendor}" == "suse"
PreReq:         %insserv_prereq
%endif
Requires:       %{name} = %{version}
Requires:       syslog-ng
Requires:       perl(NetAddr::IP::Util)
Requires:       perl(DBI)
Requires:       perl(DBD::mysql)
Conflicts:	%{name}-rsyslog-pgsql
Conflicts:	%{name}-rsyslog-mysql

%description syslog-ng2mysql
Because direct from syslog-ng to database is not the fastest, a small perl
daemon (syslog-ng2mysql.pl) was introduced. Syslog-ng2mysql.pl opens a
unix-pipe on the one side and uses DBI on the other to write data to MySQL.

%package rsyslog-mysql
Summary:	rsyslog config for writing directly into the MySQL database
Group:		Applications/System
Requires:       %{name} = %{version}
Requires:	rsyslog
Requires:       perl(NetAddr::IP::Util)
Requires:	perl-DBD-MySQL
%if "%{_vendor}" == "suse"
Requires: 	rsyslog-module-mysql
%endif
%if "%{_vendor}" == "redhat"
Requires:	rsyslog-mysql
%endif
Conflicts:	%{name}-rsyslog-pgsql
Conflicts:	%{name}-syslog-ng2mysql

%description rsyslog-mysql
There is no daemon available like in syslog-ng2mysql, but a direct rsyslog configuration.


%package rsyslog-pgsql
Summary:	rsyslog config for writing directly into the PostgreSQL database
Group:		Applications/System
Requires:       %{name} = %{version}
Requires:	rsyslog
Requires:       perl(NetAddr::IP::Util)
Requires:	perl-DBD-Pg
%if "%{_vendor}" == "suse"
Requires: 	rsyslog-module-pgsql
%endif
%if "%{_vendor}" == "redhat"
Requires:	rsyslog-pgsql
%endif
Conflicts:	%{name}-rsyslog-mysql
Conflicts:	%{name}-syslog-ng2mysql

%description rsyslog-pgsql
There is no daemon available, but a direct rsyslog configuration.


%package plugin
Summary:        Check plugin for Icinga/Nagios
Group:          Applications/System
Requires:       %{name} = %{version}
Requires:       python >= 2.4
%if "%{_vendor}" == "redhat"
Requires:	MySQL-python
%endif
%if "%{_vendor}" == "suse"
Requires:       python-mysql
%endif

%description plugin
The job of checking the EventDB for entries is done by this plugin. 


%package icinga-web
Summary:	Icinga Web Module for EventDB
Group:		Applications/System
Requires:       %{name} = %{version}
Requires: 	icinga-web >= 1.7.0

%description icinga-web
EventDB Icinga Web Module Integration. 


%prep
#%setup -qn eventdb-eventdb
%setup -qn %{name}-%{version}

find -name "*.gitignore" | xargs rm -rf

%build
#

%install
%{__rm} -rf %{buildroot}

mkdir -p %{buildroot}%{webdir}
mkdir -p %{buildroot}%{_defaultdocdir}/%{name}
mkdir -p %{buildroot}%{_sysconfdir}/%{name}
# install documentation
cp -r db %{buildroot}%{_defaultdocdir}/%{name}/
cp -r doc/* %{buildroot}%{_defaultdocdir}/%{name}/
# install plugin including config
install -Dm755 plugin/check_eventdb.py %{buildroot}/%{libexecdir}/check_eventdb.py
# command definition
cat >>%{buildroot}/%{_defaultdocdir}/%{name}/check_eventdb_cmd.cfg<<EOF
define command {
  command_name         check_eventdb
  command_line         $USER1$/check_eventdb.py --dbuser=eventdb --dbpass=eventdb -H $HOSTNAME$ $ARG1$
}
EOF
# service definition
cat >>%{buildroot}/%{_defaultdocdir}/%{name}/check_eventdb_service.cfg<<EOF
define service {
   use                  generic-service
   host_name            host1
   service_description  eventdb_error
   check_command        check_eventdb!--facility 4 --priority 0,1,2 -m "%ssh%" -w 1 -c 2 --label=ssh_errors
}
EOF
#
# install syslog-ng2mysql.pl
pushd agenten/syslog-ng
%if "%{_vendor}" == "suse"
install -Dm755 mysql/init/syslog-ng2mysql.init-sles %buildroot/%{_sysconfdir}/init.d/syslog-ng2mysql
%endif
%if "%{_vendor}" == "redhat"
install -Dm755 mysql/init/syslog-ng2mysql.init-rhel %buildroot/%{_sysconfdir}/init.d/syslog-ng2mysql
%endif
# change user and location
sed -i -e 's|USER=icinga|USER=eventdb|' -e 's|GROUP=icinga|GROUP=eventdb|' -e 's|DAEMON=/usr/local/icinga/contrib/|DAEMON=%{_bindir}/|' %{buildroot}%{_sysconfdir}/init.d/syslog-ng2*

install -m644 syslog-ng.conf %{buildroot}/%{_defaultdocdir}/%{name}/
mkdir -p %{buildroot}/%{_defaultdocdir}/%{name}/agent-examples/
cp -r ../not_supported_yet/* %{buildroot}/%{_defaultdocdir}/%{name}/agent-examples/
install -Dm755 mysql/syslog-ng2mysql.pl %{buildroot}/%{_bindir}/syslog-ng2mysql.pl
sed -i -e 's|/usr/local/icinga/var/rw/syslog-ng.pipe|%{_var}/spool/%{name}/syslog-ng.pipe|' %{buildroot}%{_bindir}/syslog-ng2*.pl
popd

# create directory for our syslog-ng.pipe
mkdir -p %{buildroot}%{_var}/spool/%{name}

# rsyslog config
install -Dm644 agenten/rsyslog/mysql/rsyslog-eventdb.conf %buildroot/%{_sysconfdir}/rsyslog.d/eventdb-mysql.conf
install -Dm644 agenten/rsyslog/pgsql/rsyslog-eventdb.conf %buildroot/%{_sysconfdir}/rsyslog.d/eventdb-pgsql.conf

#
# classicWeb
install -Dm644 classicWeb/index.php %{buildroot}/%{webdir}/index.php
mkdir -p %{buildroot}/%{apache2_sysconfdir}
install -Dm644 etc/apache2/eventdb.conf %{buildroot}/%{apache2_sysconfdir}/%{name}.conf
#
# icinga web module (manual copy, no phing call)
%{__mkdir_p} %{buildroot}%{icingawebdir}/app/modules
%{__cp} -r icinga-cronk/EventDB %{buildroot}%{icingawebdir}/app/modules/

%clean
%{__rm} -rf %{buildroot}


%pre syslog-ng2mysql
%{_sbindir}/groupadd eventdb 2> /dev/null || :
%{_sbindir}/useradd -c "eventdb" -s /sbin/nologin -r -d /nonexistent -g eventdb eventdb 2> /dev/null || :

%post www
%if "%{_vendor}" == "suse"
%restart_on_update %{webserver}
%endif
%if "%{_vendor}" == "redhat"
%{_sysconfdir}/init.d/%{webserver} restart
%endif

%postun www
%if "%{_vendor}" == "suse"
%restart_on_update %{webserver}
%endif
%if "%{_vendor}" == "redhat"
%{_sysconfdir}/init.d/%{webserver} restart
%endif

%postun syslog-ng2mysql
%if "%{_vendor}" == "suse"
%restart_on_update syslog-ng2mysql
#insserv_cleanup
%endif

%post syslog-ng2mysql
%if "%{_vendor}" == "suse"
%{fillup_and_insserv -f -y syslog-ng2mysql}
%endif

%post rsyslog-mysql
%{_sysconfdir}/init.d/rsyslog restart

%postun rsyslog-mysql
%{_sysconfdir}/init.d/rsyslog restart

%post rsyslog-pgsql
%{_sysconfdir}/init.d/rsyslog restart

%postun rsyslog-pgsql
%{_sysconfdir}/init.d/rsyslog restart

%post icinga-web
if [ -x %{clearcache} ]; then %{clearcache}; fi

%files
%defattr(-,root,root,-)
%if "%{_vendor}" == "redhat"
%doc doc/gpl.txt doc/INSTALL doc/README.RHEL
%endif
%if "%{_vendor}" == "suse"
%doc doc/gpl.txt doc/INSTALL doc/README.SUSE
%endif
%defattr(0644,root,root,0755)
%doc %{_defaultdocdir}/%name/
%exclude %{_defaultdocdir}/%{name}/agent-examples
%exclude %{_defaultdocdir}/%{name}/check_eventdb*.cfg
%exclude %{_defaultdocdir}/%{name}/syslog-ng.conf

%files plugin
%defattr(-,root,root)
%dir /usr/lib/nagios
%dir %{libexecdir}
%doc %{_defaultdocdir}/%{name}/check_eventdb*.cfg
%{libexecdir}/check_eventdb.py*

%files syslog-ng2mysql
%defattr(-,root,root)
%doc %{_defaultdocdir}/%{name}/syslog-ng.conf
%dir %{_defaultdocdir}/%{name}/agent-examples
%dir %{_var}/spool/%{name}
%doc %{_defaultdocdir}/%{name}/agent-examples/*
%config %{_sysconfdir}/init.d/syslog-ng2mysql
%{_bindir}/syslog-ng2mysql.pl

%files rsyslog-mysql
%defattr(-,root,root)
%config(noreplace) %{_sysconfdir}/rsyslog.d/eventdb-mysql.conf

%files rsyslog-pgsql
%defattr(-,root,root)
%config(noreplace) %{_sysconfdir}/rsyslog.d/eventdb-pgsql.conf

%files www
%defattr(-,root,root)
%dir %{_datadir}/%{name}
%{_datadir}/%{name}/*
%{apache2_sysconfdir}/%{name}.conf

%files icinga-web
%defattr(-,root,root)
%{_datadir}/icinga-web/app/modules/EventDB
%config(noreplace) %{_datadir}/icinga-web/app/modules/EventDB/config/autoload.xml
%config(noreplace) %{_datadir}/icinga-web/app/modules/EventDB/config/config_handlers.xml
%config(noreplace) %{_datadir}/icinga-web/app/modules/EventDB/config/cronks.xml
%config(noreplace) %{_datadir}/icinga-web/app/modules/EventDB/config/css.xml
%config(noreplace) %{_datadir}/icinga-web/app/modules/EventDB/config/databases.xml
%config(noreplace) %{_datadir}/icinga-web/app/modules/EventDB/config/javascript.xml
%config(noreplace) %{_datadir}/icinga-web/app/modules/EventDB/config/module.xml
%config(noreplace) %{_datadir}/icinga-web/app/modules/EventDB/config/routing.xml
%config(noreplace) %{_datadir}/icinga-web/app/modules/EventDB/config/validators.xml

%attr(0755,%{apacheuser},%{apachegroup}) %{_datadir}/icinga-web/app/modules/EventDB/views/
%attr(0755,%{apacheuser},%{apachegroup}) %{_datadir}/icinga-web/app/modules/EventDB/templates/

%changelog
* Wed Jul 24 2013 michael.friedrich@netways.de
- bump to 2.0.6

* Mon May 20 2013 michael.friedrich@netways.de
- updated for 2.0.5
- fix rpmlint errors
- drop broken -oracle sub packages, not needed

* Wed Apr 24 2013 jannis.mosshammer@netways.de
- updated for 2.0.5beta

* Wed Feb 06 2013 christian.dengler@netways.de
- add subpackage to support rsyslog-pgsql
- add schema for pgsql
- add custom tag
- add Require to perl DBI driver (for the DB cleanup scripts)

* Wed Jan 30 2013 christian.dengler@netways.de
- add subpackage to support syslog for oracle

* Mon Jan 28 2013 christian.dengler@netways.de
- fix clearcache directory
- add config for webserver apache2

* Wed Dec 12 2012 michael.friedrich@netways.de
- updated for 2.0.4rc
- created -icinga-web for the seperated integration
- changed default user to icinga

