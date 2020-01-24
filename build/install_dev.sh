#!/bin/bash
# fileencoding=utf8
# lineends=linux

echo -e "\n### Creating new dev directory #############################################################################################"
#mkdir -v ~/ffg_dev
#cd ~/ffg_dev/rcr_repo/build

echo -e "\n### Cloning Dev Git branch #################################################################################################"
#git clone --recurse-submodules https://bitbucket.org/t4sg/latam-ffg-2019-fundacion-red-comunidades-rurales/src/develop/ rcr_repo

#chdmod +x ~/ffg_dev/rcr_repo/build/install_dev.sh

echo -e "\n### APT updates and upgrades ###############################################################################################"
sudo apt update
sudo apt upgrade -y
sudo apt dist-upgrade -y
sudo apt autoremove -y

echo -e "\n### Installing NodeJS 12 ###################################################################################################"
sudo apt install build-essential apt-transport-https lsb-release ca-certificates curl -y
curl -sL https://deb.nodesource.com/setup_12.x | sudo -E bash -
sudo apt install nodejs -y

echo -e "\n### Installing Apache ######################################################################################################"
sudo apt install apache2 apache2-utils -y

echo -e "\n### Installing PHP and CGI libraries #######################################################################################"
sudo apt install libapache2-mod-php7.2 php7.2 php7.2-fpm php7.2-common php7.2-mysql php7.2-xml php7.2-xmlrpc php7.2-curl php7.2-gd php7.2-imagick php7.2-cli php7.2-dev php7.2-imap php7.2-mbstring php7.2-soap php7.2-zip php7.2-bcmath php7.2-bz2  php7.2-intl php7.2-pspell  php7.2-sqlite3  php-dev -y

echo -e "\n### Backup of original configuration files #################################################################################"
today=`date +%Y%m%d-%H%M%S`
sudo cp -v /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/000-default.conf_bk.$today
sudo cp -v /etc/apache2/sites-available/default-ssl.conf /etc/apache2/sites-available/default-ssl.conf_bk.$today
sudo cp -v /etc/apache2/apache2.conf /etc/apache2/apache2.conf_bk.$today
sudo cp -v /etc/php/7.2/fpm/php.ini /etc/php/7.2/fpm/php.ini_bk.$today

echo -e "\n### Copying new configuration files ########################################################################################"
sudo cp -v ~/ffg_dev/rcr_repo/build/configs/000-default.conf /etc/apache2/sites-available/000-default.conf
sudo cp -v ~/ffg_dev/rcr_repo/build/configs/default-ssl.conf /etc/apache2/sites-available/default-ssl.conf
sudo cp -v ~/ffg_dev/rcr_repo/build/configs/apache2.conf /etc/apache2/apache2.conf
sudo cp -v ~/ffg_dev/rcr_repo/build/configs/php.ini /etc/php/7.2/fpm/php.ini

echo -e "\n### Activating Apache PHP CGI modules and SSL ##############################################################################"
sudo a2dismod php7.2
sudo a2enmod proxy_fcgi setenvif
sudo a2enconf php7.2-fpm
sudo a2ensite default-ssl
sudo a2enmod ssl
sudo a2enmod rewrite

echo -e "\n### Adding local DNS #######################################################################################################"
echo -e "127.0.0.1       desa.poblaciones.org" | sudo tee -a /etc/hosts
uniq /etc/hosts | sudo tee /etc/hosts

echo -e "\n### Installing dependencies ################################################################################################"
cd ~/ffg_dev/rcr_repo/frontend
npm install
cd ~/ffg_dev/rcr_repo/services
php composer.phar install

cp -v ~/ffg_dev/rcr_repo/build/configs/settings.php ~/ffg_dev/rcr_repo/services/config
cp -v ~/ffg_dev/rcr_repo/build/configs/.htaccess ~/ffg_dev/rcr_repo/services/web
cp -v ~/ffg_dev/rcr_repo/build/configs/dev.env.js ~/ffg_dev/rcr_repo/frontend/config

cd ~/ffg_dev/rcr_repo/build
chmod +x ~/ffg_dev/rcr_repo/build/build.sh
chmod +x ~/ffg_dev/rcr_repo/build/build_local.sh
./build_local.sh
sudo usermod -a -G www-data force
sudo usermod -a -G force www-data
