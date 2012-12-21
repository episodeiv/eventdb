#
# spec file for package eventdb
#
# Initial revision from
# https://build.opensuse.org/package/show?package=eventdb&project=server%3Amonitoring 
#
# Revised and updated by Michael Friedrich
# (c) 2012 Netways GmbH
#
# This file and all modifications and additions to the pristine
# package are under the same license as the package itsels

Name:           eventdb2
Summary:        Manage and administrate recipient events for Icinga and Nagios
Version:        2.0.4rc
Release:        1
Url:            https://www.netways.org/projects/show/eventdb
License:        GPL v2 or later
Group:          System/Monitoring
Source0:        eventdb-%version.tar.gz
%if "%{_vendor}" == "suse"
Source1:        syslog-ng-ng2mysql.init
%endif
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
BuildRequires: 	php >= 5.2
%endif
BuildArch:      noarch
BuildRoot:      %{_tmppath}/%{name}-%{version}-build
%define         nsusr icinga
%define         nsgrp icinga
%define         libexecdir %{_prefix}/lib/nagios/plugins
%define         webdir %{_datadir}/%{name}

%if "%{_vendor}" == "redhat"
%define         apacheuser apache
%define         apachegroup apache
%endif
%if "%{_vendor}" == "suse"
%define         apacheuser wwwrun
%define         apachegroup www
%endif
%define         icingawebdir /usr/share/icinga-web
%define         clearcache %{_sbindir}/icinga-web-clearcache
%define         docdir %{_datadir}/doc/%{name}


%description
EventDB is a Tool for an easy handling of event based data, e.g. SNMP Traps,
eMails or logfiles in monitoring systems like Icinga/Nagios. It has multiple plugins for different data sources and can be extended for new data input channels. The Classic webinterface allows users to search, filter and acknowledge all events. For Icinga Web, there's a seperated cronk integration available.
Integration in Icinga/Nagios is made through a Icinga/Nagios plugin, which is also part of this package.


%package www
Summary:        Classic Web interface
Group:          System/Monitoring
%if "%{_vendor}" == "suse"
PreReq:         apache2
%endif
Requires:       %{name} = %{version}
Requires:       php-mysql
Requires:       php-mbstring

%description www
This package contains all parts for the classic web interface of eventdb.


%package syslog-ng2mysql
Summary:        Daemon for writing directly into the MySQL database 
Group:          System/Daemons
%if "%{_vendor}" == "suse"
PreReq:         %insserv_prereq
%endif
Requires:       %{name} = %{version}
Requires:       syslog-ng
Requires:       perl(NetAddr::IP::Util)
Requires:       perl(DBD::mysql)
Requires:       perl(DBI)
Conflicts:	%{name}-rsyslog-mysql

%description syslog-ng2mysql
Because direct from syslog-ng to database is not the fastest, a small perl
daemon (syslog-ng2mysql.pl) was introduced. Syslog-ng2mysql.pl opens a
unix-pipe on the one side and uses DBI on the other to write data to MySQL.

%package rsyslog-mysql
Summary:	RSyslog config for writing directly into the MySQL database
Group:		System/Monitoring
Requires:       %{name} = %{version}
Requires:	rsyslog
Requires: 	rsyslog-module-mysql
Conflicts:	%{name}-syslog-ng2mysql

%description rsyslog-mysql
There is no daemon available like in syslog-ng2mysql, but a direct rsyslog configuration.

%package plugin
Summary:        Check plugin for Icinga/Nagios
Group:          System/Monitoring
Requires:       %{name} = %{version}
Requires:       python >= 2.4
Requires:       python-mysql

%description plugin
The job of checking the EventDB for entries is done by this plugin. 

%package icinga-web
Summary:	Icinga Web Module for EventDB
Group:		System/Monitoring
Requires:       %{name} = %{version}
Requires: 	icinga-web >= 1.7.0

%description icinga-web
EventDB Icinga Web Module Integration. 

%prep
%setup -q -n eventdb-eventdb
find -name "*.gitignore" | xargs rm -rf

%build
#

%install
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
install -Dm755 %{SOURCE1} %buildroot/%{_sysconfdir}/init.d/syslog-ng2mysql
%endif
install -m644 syslog-ng.conf %{buildroot}/%{_defaultdocdir}/%{name}/
mkdir -p %{buildroot}/%{_defaultdocdir}/%{name}/agent-examples/
cp -r ../not_supported_yet/* %{buildroot}/%{_defaultdocdir}/%{name}/agent-examples/
install -Dm755 mysql/syslog-ng2mysql.pl %{buildroot}/%{_bindir}/syslog-ng2mysql.pl
popd

# rsyslog config
install -Dm644 agenten/rsyslog/rsyslog-eventdb.conf %buildroot/%{_sysconfdir}/rsyslog.d/eventdb.conf

#
# classicWeb
install -Dm644 classicWeb/index.php %{buildroot}/%{webdir}/index.php
#
# icinga web module (manual copy, no phing call)
%{__mkdir_p} %{buildroot}%{icingawebdir}/app/modules
%{__cp} -r icinga-cronk/EventDB %{buildroot}%{icingawebdir}/app/modules/
# use mysql by default
%{__rm} -rf %{buildroot}%{icingawebdir}/app/modules/EventDB/lib/database/generated/oracle

# cleanup python 
%{__rm} -f %{libexecdir}/check_eventdb.pyc
%{__rm} -f %{libexecdir}/check_eventdb.pyo

%clean
rm -rf %{buildroot}

%post www
if [ x"$1" == x"1" ]; then
%if "%{_vendor}" == "suse"
    # this is the initial installation: enable eventdb
    test -x %{_sbindir}/a2enflag && %{_sbindir}/a2enflag EVENTDB >/dev/null
%endif
fi

%postun www
if [ x"$1" == x"0" ]; then
%if "%{_vendor}" == "suse"
    # deinstallation of the package - remove the apache flag
    test -x %{_sbindir}/a2disflag && %{_sbindir}/a2disflag EVENTDB >/dev/null
%endif
fi

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
/etc/init.d/rsyslog restart

%post icinga-web
if [[ -x %{clearcache} ]]; then %{clearcache}; fi


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
%dir %{_prefix}/lib/nagios
%dir %{libexecdir}
%doc %{_defaultdocdir}/%{name}/check_eventdb*.cfg
%{libexecdir}/check_eventdb.py
# rhel creates bytecompiled python foo, exclude that
%if "%{_vendor}" == "redhat"
%exclude %{libexecdir}/check_eventdb.pyc
%exclude %{libexecdir}/check_eventdb.pyo
%endif

%files syslog-ng2mysql
%defattr(-,root,root)
%doc %{_defaultdocdir}/%{name}/syslog-ng.conf
%dir %{_defaultdocdir}/%{name}/agent-examples
%doc %{_defaultdocdir}/%{name}/agent-examples/*
%if "%{_vendor}" == "suse"
%config %{_sysconfdir}/init.d/syslog-ng2mysql
%endif
%{_bindir}/syslog-ng2mysql.pl

%files rsyslog-mysql
%defattr(-,root,root)
%config(noreplace) %{_sysconfdir}/rsyslog.d/eventdb.conf

%files www
%defattr(-,root,root)
%dir %{_datadir}/%{name}
%{_datadir}/%{name}/*

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
* Wed Dec 12 2012 michael.friedrich@netways.de
- updated for 2.0.4rc
- created -icinga-web for the seperated integration
- changed default user to icinga

