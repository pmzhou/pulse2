#!/usr/bin/python
# -*- coding: utf-8; -*-
#
# (c) 2013 Mandriva, http://www.mandriva.com/
#
# This file is part of Pulse 2, http://pulse2.mandriva.org
#
# Pulse 2 is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# Pulse 2 is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Pulse 2.  If not, see <http://www.gnu.org/licenses/>.

"""
Common methods called from PXE with response processing.

This module is replacing a part of old LRS imaging server and its set of hooks.
Processing and functionnality of major part of functions is preserved,
only functions using temporary text files to exchange longer strings
are optimized to direct communication by variables.
"""

import logging

from twisted.internet import reactor, task
from twisted.internet.defer import succeed, Deferred
from twisted.web.client import getPage

from pulse2.package_server.imaging.pxe.parser import PXEMethodParser, assign
from pulse2.package_server.imaging.pxe.parser import LOG_LEVEL, LOG_STATE
from pulse2.package_server.imaging.pxe.tracking import EntryTracking
from pulse2.package_server.config import P2PServerCP
from pulse2.imaging.bootinventory import BootInventory
import re
import xml.etree.ElementTree as ET  # form XML Building
import time


class PXEImagingApi (PXEMethodParser):
    """
    A frame to recognize the method definitions to build.

    Names and argumets of methods will be extracted and validated
    before calling them by PXEMethodParser.

    Rules to recognize instance methods as "RPC-like callables" :

    - decorated with <@assign> decorator having related argument to identify
    - all the arguments of that methods must be declared into
      ArgumentContainer class as properties (with the same name)
    - don't forget to initialize PXEMethodParser instance.
    """
    api = None

    lasttime = 0
    lastfile = 0


    def set_api(self, api):
        self.api = api

    def __init__(self, config):
        PXEMethodParser.__init__(self)
        self.config = config

    # argument of decorator @assign is identifying each method
    # which can be executed.
    # this argument is ord value of first byte of packet

    # ------------------------ computer register ------------------------------

    @assign(0xAD)
    def computerRegister(self, mac, hostname, ip_address, password=None):
        """
        Machine inscription by PXE imaging client.

        If the GLPI backend is used, a minimal inventory is sent to glpiproxy
        two seconds before the inscription by imaging backend.

        @param mac: MAC address
        @type mac: str

        @param hostname: hostname of inventoried machine
        @type hostname: str

        @param ip_address: machine IP address
        @type ip_address: str

        """
        self.api.logClientAction(mac,
                                 LOG_LEVEL.DEBUG,
                                 LOG_STATE.MENU,
                                 "menu identification")

        if self.config.imaging_api["glpi_mode"] :

            d = task.deferLater(reactor, 0, self.glpi_register, mac, hostname, ip_address)
            d.addCallback(self._computerRegister, hostname, mac, 2)
            d.addErrback(self._ebRegisterError, mac)

            return d

        else :

            return self._computerRegister(None, hostname, mac)

    @assign(0xBB)
    def computerRegisterSyslinux(self, mac, inventory, ip_address):
        """
        Minimal inventory received from PXE.

        @param mac: MAC address
        @type mac: str

        @param inventory: inventory from PXE
        @type inventory: str

        @rtype: deferred
        """
        logging.getLogger().debug("FIRST REGISTRATION TO ALLOW INVENTORY")
        m = re.search('<REQUEST>.*<\/REQUEST>', inventory)
        file_content = str(m.group(0))
        ipadress = self.ip_adressexml(file_content)
        mac1= self.mac_adressexml(file_content)
        hostnamexml = self.hostname_xml(file_content)
        inventory ="<?xml version=\"1.0\" encoding=\"utf-8\"?>\n%s"%(file_content)
        ip_address = ipadress
        mac =  mac1
        return self.send_inventory(inventory, hostnamexml)


    def _computerRegister(self, result, hostname, mac, delay=0):
        """
        Machine inscription by imaging backend.

        @param result: used only if called as callback
        @type result: bool

        @param mac: MAC address
        @type mac: str

        @param hostname: hostname of inventoried machine
        @type hostname: str

        @param ip_address: machine IP address
        @type ip_address: str
        """

        d = task.deferLater(reactor, delay, self.api.computerRegister, hostname, mac)
        d.addCallback(self._cbRegisterOk, mac)
        d.addErrback(self._ebRegisterError, mac)

        return d


    def _cbRegisterOk (self, result, mac):
        self.api.logClientAction(mac,
                                 LOG_LEVEL.DEBUG,
                                 LOG_STATE.MENU,
                                 "identification success")

    def _ebRegisterError (self, failure, mac):
        self.api.logClientAction(mac,
                                 LOG_LEVEL.WARNING,
                                 LOG_STATE.MENU,
                                 "identification failure")

    def ipV4toDecimal(self, ipv4):
        d = ipv4.split('.')
        return (int(d[0])*256*256*256) + (int(d[1])*256*256) + (int(d[2])*256) +int(d[3])

    def decimaltoIpV4(self,ipdecimal):
        a=float(ipdecimal)/(256*256*256)
        b = (a - int(a))*256
        c = (b - int(b))*256
        d = (c - int(c))*256
        return "%s.%s.%s.%s"%(int(a),int(b),int(c),int(d))

    def subnetreseau(self, adressmachine, mask):
        adressmachine = adressmachine.split(":")[0]
        reseaumachine = self.ipV4toDecimal(adressmachine) &  self.ipV4toDecimal(mask)
        return self.decimaltoIpV4(reseaumachine)

    def glpi_register(self, mac, hostname, ip_address):
        """
        Computer register sending a minimal inventory

        @param mac: MAC address
        @type mac: str

        @param hostname: hostname of inventoried machine
        @type hostname: str

        @param ip_address: machine IP address
        @type ip_address: str

        """
        logging.getLogger().debug("glpi_register")
        boot_inv = BootInventory()
        boot_inv.macaddr_info = mac
        boot_inv.ipaddr_info = {'ip': ip_address, 'port': 0}
        # add information network in xml glpi
        boot_inv.netmask_info = P2PServerCP().public_mask
        boot_inv.subnet_info  = self.subnetreseau(boot_inv.ipaddr_info['ip'],boot_inv.netmask_info)
        inventory = boot_inv.dumpOCS(hostname, "root")
        return self.send_inventory(inventory, hostname)

    @assign(0xAF)
    def clientAuth(self, mac, password):
        """
        Authentification on PXE console.

        @param mac: MAC address
        @type mac: str

        @param password: prompted password on PXE
        @type password: str

        @return: "ok" if correct, otherwise "ko"
        @rtype: str
        """
        def __sha512_crypt_password(password):
            if not password: return ''
            import crypt, base64
            passphrase = '$6$DzmCpUs3$'
            return crypt.crypt(password, passphrase)

        self.api.logClientAction(mac,
                                 LOG_LEVEL.INFO,
                                 LOG_STATE.IDENTITY,
                                 "menu identification request")


        if __sha512_crypt_password(password) == P2PServerCP().pxe_password:
            logging.getLogger().debug("PXE Proxy: client authentification OK")
            return succeed('ok')
        else :
            logging.getLogger().warn("PXE Proxy: client authentification FAILED")
            return succeed("ko")

    def ip_adressexml(self, file_content ):
        root = ET.fromstring(file_content)
        for child in root:
            if child.tag == "CONTENT":
                for cc in child:
                    if cc.tag == "NETWORKS":
                        for dd in cc:
                            if dd.tag == "IPADDRESS":
                                return dd.text
        return ""


    def mac_adressexml(self, file_content ):
        root = ET.fromstring(file_content)
        for child in root:
            if child.tag == "CONTENT":
                for cc in child:
                    if cc.tag == "NETWORKS":
                        for dd in cc:
                            if dd.tag == "MACADDR":
                                return dd.text
        return ""

    def hostname_xml(self, file_content ):
        root = ET.fromstring(file_content)
        for child in root:
            if child.tag == "CONTENT":
                for cc in child:
                    if cc.tag == "HARDWARE":
                        for dd in cc:
                            if dd.tag == "NAME":
                                return dd.text
        return ""

    @assign(0xBA)
    def InventorySysLinux(self, mac, inventory, ip_address):
        """
        Minimal inventory received from PXE.

        @param mac: MAC address
        @type mac: str

        @param inventory: inventory from PXE
        @type inventory: str

        @rtype: deferred
        """
        logging.getLogger().debug("INJECT INVENTORY NEXT HOSTNAME AND ENTITY")
        m = re.search('<REQUEST>.*<\/REQUEST>', inventory)
        file_content = str(m.group(0))

        ipadress = self.ip_adressexml(file_content)
        mac1= self.mac_adressexml(file_content)
        hostnamexml = self.hostname_xml(file_content)

        inventory ="<?xml version=\"1.0\" encoding=\"utf-8\"?>\n%s"%(file_content)
        ip_address = ipadress
        mac =  mac1

        self.api.logClientAction(mac,
                                 LOG_LEVEL.DEBUG,
                                 LOG_STATE.MENU,
                                 "boot menu shown")
        if not "Mc" in inventory :
            inventory = inventory + "\nMAC Address:%s\n" % mac
        else :
            inventory = inventory.replace("Mc", "MAC Address")
        parsed_inventory1 = BootInventory()
        parsed_inventory1.initialise(file_content)
        parsed_inventory = parsed_inventory1.dump()
        self.api.logClientAction(mac,
                                 LOG_LEVEL.DEBUG,
                                 LOG_STATE.MENU,
                                 "boot menu shown")
        d = self.api.injectInventory(mac, parsed_inventory)

        # 2nd step - send inventory by HTTP POST to inventory server
        d.addCallback(self._injectedInventoryOk, mac, inventory)
        d.addErrback(self._injectedInventoryError)
        #self.send_inventory(parsed_inventory1., hostname)
        return d
    #  ------------------------ process inventory ---------------------------
    @assign(0xAA)
    def injectInventory(self, mac, inventory, ip_address):
        """
        Minimal inventory received from PXE.

        @param mac: MAC address
        @type mac: str

        @param inventory: inventory from PXE
        @type inventory: str

        @rtype: deferred
        """
        # XXX - A little hack to add networking info on GLPI mode
        logging.getLogger().debug("injectInventory mac %s ip : %s \n\n" %(mac,ip_address))
        if not "Mc" in inventory :
            inventory = inventory + "\nMAC Address:%s\n" % mac
        else :
            inventory = inventory.replace("Mc", "MAC Address")
        if not "IPADDR" in inventory :
            inventory = inventory + "\nIP Address:%\n" % ip_address
        else :
            inventory = inventory.replace("IPADDR", "IP Address")
        inventory = [i.strip(' \t\n\r').lstrip('\x00\x00').strip() for i in inventory.split("\n")]

        logging.getLogger().debug("low level inventory: %s\n" %(inventory))
        parsed_inventory1 = BootInventory(inventory)
        parsed_inventory = parsed_inventory1.dump()
        self.api.logClientAction(mac,
                                 LOG_LEVEL.DEBUG,
                                 LOG_STATE.MENU,
                                 "boot menu shown")
        # 1st step - inject inventory trough imaging (only disk info)
        d = self.api.injectInventory(mac, parsed_inventory)

        # 2nd step - send inventory by HTTP POST to inventory server
        d.addCallback(self._injectedInventoryOk, mac, inventory)
        d.addErrback(self._injectedInventoryError)
        #self.send_inventory(parsed_inventory1., hostname)
        return d

    def _injectedInventoryError(self, failure):
        """ Inject inventory failed """
        logging.getLogger().error("PXE Proxy: something were wrong while injecting inventory: %s" % str(failure))

    def _injectedInventoryOk (self, result, mac, inventory):
        """
        Result parsing callback after inventory inject.

        If inventory inject trough imaging is successfull,
        following computer info is needed to send our inventory
        to inventory server.

        @param result: inject inventory result
        @type result: list

        @param mac: MAC address
        @type mac: str

        @param inventory: inventory from PXE
        @type inventory: str
        """
        if isinstance(result, list) and len(result) > 0 \
                and isinstance(result[0], str) and result[0] == 'PULSE2_ERR':
            logging.getLogger().error("PXE Proxy: Error code = %d when inject inventory" % (result[1]))
            return None
        else:
            logging.getLogger().debug("PXE Proxy: Hardware inventory injected successfully into imaging")

            # need the hostname and entity to send this inventory
            d = self.api.getComputerByMac(mac)
            d.addCallback(self._injectedInventorySend, mac, inventory)
            d.addErrback(self._injectedInventoryErrorGetComputer, mac)


    def _injectedInventoryErrorGetComputer(self, failure, mac):
        """ An error occured while getting the hostname """

        logging.getLogger().warn("PXE Proxy: inject inventory - get hostname failed: %s" % str(failure))

        self.api.logClientAction(mac,
                                 LOG_LEVEL.ERR,
                                 LOG_STATE.INVENTORY,
                                 "hardware inventory not stored")

    def changEntityAndHostName(self, xml, entity=None, hostname=None):
        root = ET.fromstring(xml)
        for child in root:
            if child.tag == "TAG" and entity != None:
                child.text = entity
            elif child.tag == "CONTENT" and hostname != None:
                for dd in child:
                    if dd.tag == "HARDWARE":
                        for ee in dd:
                            if ee.tag == "NAME":
                                ee.text = hostname
        return ET.tostring(root)

    def changdeviceid(self, xml,hostname=None):
        date1=time.strftime("%Y-%m-%d-%H-%M-%S")
        logdate=time.strftime("%Y-%m-%d %H:%M:%S")
        deviceid = "%s-%s"%(hostname, date1)
        root = ET.fromstring(xml)
        for child in root:
            if child.tag == "DEVICEID" and hostname != None:
                child.text = deviceid
            elif child.tag == "CONTENT" :
                for dd in child:
                    if dd.tag == "ACCESSLOG":
                        for ee in dd:
                            if ee.tag == "LOGDATE":
                                ee.text = logdate
        return ET.tostring(root)

    def _injectedInventorySend (self, computer, mac, inventory):
        """
        Inventory sending by HTTP POST to inventory server.

        @param computer: ComputerManager container
        @type computer: dist

        @param mac: MAC address
        @type mac: str

        @param inventory: inventory from PXE
        @type inventory: str

        """
        self.api.logClientAction(mac,
                                 LOG_LEVEL.DEBUG,
                                 LOG_STATE.INVENTORY,
                                 "hardware inventory received")
        if not isinstance(computer, dict) :
            logging.getLogger().debug("PXE Proxy: Unknown client, ignore received inventory")
            return
        hostname = computer['shortname']
        entity = computer['entity']
        m = re.search('<REQUEST>.*<\/REQUEST>', inventory)
        file_content = str(m.group(0))
        #inventory = parsed_inventory1.changEntityAndHostName(file_content,entity,hostname)
        file_content = self.changEntityAndHostName(file_content,entity,hostname)
        inventory = self.changdeviceid(file_content,hostname)
        inventory ='<?xml version="1.0" encoding="utf-8"?>'+inventory
        logging.getLogger().debug("send invotory depuis _injectedInventorySend")
        d = self.send_inventory(inventory, hostname)
        @d.addCallback
        def _cb(result):
            self.api.logClientAction(mac,
                                     LOG_LEVEL.INFO,
                                     LOG_STATE.INVENTORY,
                                     "hardware inventory updated")
        @d.addErrback
        def _eb(failure):
            self.api.logClientAction(mac,
                                     LOG_LEVEL.WARNING,
                                     LOG_STATE.INVENTORY,
                                     "hardware inventory not updated")

        self.api.logClientAction(mac,
                                 LOG_LEVEL.DEBUG,
                                 LOG_STATE.MENU,
                                 "menu identification")

        m = re.search('<REQUEST>.*<\/REQUEST>', inventory)
        file_content = str(m.group(0))
        ipadress = self.ip_adressexml(file_content)
        ip_address = ipadress
        if self.config.imaging_api["glpi_mode"] :
            d = task.deferLater(reactor, 0, self.glpi_register, mac, hostname, ip_address)
            d.addCallback(self._computerRegister, hostname, mac, 2)
            d.addErrback(self._ebRegisterError, mac)
            return d
        else :
            return self._computerRegister(None, hostname, mac)

    def send_inventory(self, inventory, hostname):
        """
        Sending the inventory on FusionInventory (XML) format.

        @param inventory: inventory to send
        @type inventory: str

        @param hostname: hostname of inventoried machine
        @type hostname: str
        """

        url = "http://"
        try:

            if self.config.imaging_api["inventory_enablessl"]:
                protocol = "https"
            else :
                protocol = "http"

            url = "%s://%s:%d/" % (protocol,
                                   self.config.imaging_api["inventory_host"],
                                   self.config.imaging_api["inventory_port"])

            # POST the inventory to the inventory server
            logging.getLogger().debug("PXE Proxy: PXE inventory forwarded to inventory server at %s" % url)
            logging.getLogger().debug("POST the inventory to the inventory server \nINVENTORY\n %s" % inventory)
            d = getPage(url,
                        method='POST',
                        postdata=inventory,
                        headers={
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'Content-Length': str(len(inventory)),
                            'User-Agent': 'Pulse2 Imaging server inventory hook'
                        },
                       )


            @d.addCallback
            def _cb (result):
                if result :
                    logging.getLogger().debug("PXE Proxy: PXE inventory from client %s successfully injected" % hostname)
                return result

            return d

        except Exception, e:
            logging.getLogger().error("PXE Proxy: Unable to forward PXE inventory to inventory server at %s: %s" % (url, str(e)))
            # This method must return a Deferred
            return Deferred()


    # ----------------------- backup -------------------------------

    @assign(0xEC)
    def computerCreateImageDirectory(self, mac):
        """
        Creating the directory to stock the downloaded backup.

        @param mac: MAC address
        @type mac: str

        @rtype: deferred
        """
        self.api.logClientAction(mac,
                                 LOG_LEVEL.DEBUG,
                                 LOG_STATE.BACKUP,
                                 "image UUID request")

        d = self.api.computerCreateImageDirectory(mac)

        @d.addCallback
        def _cb(result):
            logging.getLogger().debug("PXE Proxy: create image directory result: %s" % str(result))
            self.api.logClientAction(mac,
                                     LOG_LEVEL.DEBUG,
                                     LOG_STATE.BACKUP,
                                     "image UUID sent")
            return result

        @d.addErrback
        def _eb(failure):
            logging.getLogger().warn("PXE Proxy: create image directory failed: %s" % str(failure))
            self.api.logClientAction(mac,
                                     LOG_LEVEL.ERR,
                                     LOG_STATE.BACKUP,
                                     "failed to summon an image UUID")
            return failure

        return d

    @assign(0xED)
    def imageDone(self, mac, imageUUID):
        """
        Called when backup process is terminated.

        @param mac: MAC address
        @type mac: str

        @param imageUUID: UUID of image
        @typr imageUUID: str

        @rtype: deferred

        """
        self.api.logClientAction(mac,
                                 LOG_LEVEL.DEBUG,
                                 LOG_STATE.BACKUP,
                                 "end-of-backup request: %s" % imageUUID)
        d = self.api.imageDone(mac, imageUUID)

        @d.addCallback
        def _cb (result):
            if result :
                self.api.logClientAction(mac,
                                         LOG_LEVEL.DEBUG,
                                         LOG_STATE.BACKUP,
                                         "end-of-backup success: %s" % imageUUID)
                logging.getLogger().debug("PXE Proxy: Backup process terminated")
                return "ACK"

        @d.addErrback
        def _eb (failure):
            self.api.logClientAction(mac,
                                     LOG_LEVEL.WARNING,
                                     LOG_STATE.BACKUP,
                                     "end-of-backup failure: %s" % imageUUID)
            logging.getLogger().warn("PXE Proxy: Backup process failed: %s" % str(failure))
            return "ERROR"

        return d

    @assign(0xCD)
    def computerChangeDefaultMenuItem(self, mac, num):
        """
        Menu item change.

        First step : reading of default menu entry from database,
        which will be compared with the selected menu entry.

        @param mac: MAC address of client machine
        @type mac: str

        @param entry: Menu entry order
        @type entry: int

        @return: ACK to confirm a correct reception, otherwise ERROR
        @rtype: str
        """
        self.api.logClientAction(mac,
                                 LOG_LEVEL.DEBUG,
                                 LOG_STATE.MENU,
                                 "preselected-menu-entry-change request : %d" % num)

        d = self.api.getDefaultMenuItem(mac)
        d.addCallback(self._computerChangeDefaultMenuItem, mac, num)

        @d.addErrback
        def _eb (failure):

            logging.getLogger().warn("PXE Proxy: preselected-menu-entry-change failed: %s" % str(failure))
            self.api.logClientAction(mac,
                                     LOG_LEVEL.WARNING,
                                     LOG_STATE.MENU,
                                     "preselected-menu-entry-change failed : %d" % num)
        return d


    def _computerChangeDefaultMenuItem(self, default_entry, mac, num):
        """
        Menu item change.

        Second step : To avoid the cyclic restore or backup, default menu entry
        is tested to backup/restore message occurence.
        If this test is positive, default menu entry is changed by "num" value,
        otherwise our menu entry is correctly preselected like before.

        @param default_entry: preselected position readed from database
        @type default_entry: int

        @param mac: MAC address of client machine
        @type mac: str

        @param entry: Menu entry order
        @type entry: int

        @return: ACK to confirm a correct reception, otherwise ERROR
        @rtype: str
        """
        if not mac in EntryTracking():
            return succeed("ACK")

        actual_entry, marked = EntryTracking().get(mac)
        if marked and actual_entry == int(default_entry):
            setDefaultMenuItem = True
            entry = num
        else :
            setDefaultMenuItem = False
            entry = int(default_entry)

        logging.getLogger().debug("PXE Proxy: Client %s has selected menu entry %s" % (mac, actual_entry))

        if setDefaultMenuItem:
            d = self.api.computerChangeDefaultMenuItem(mac, entry)
        else:
            d = Deferred()

        EntryTracking().delete(mac)

        @d.addCallback
        def _cb (result):
            self.api.logClientAction(mac,
                                     LOG_LEVEL.DEBUG,
                                     LOG_STATE.MENU,
                                     "preselected-menu-entry-change success : %d" % entry)

            return "ACK"

        @d.addErrback
        def _eb (failure):
            self.api.logClientAction(mac,
                                     LOG_LEVEL.WARNING,
                                     LOG_STATE.MENU,
                                     "preselected-menu-entry-change failed : %d" % num)

            return "ERROR"

        return d


    @assign(0x4C)
    def logClientAction(self, mac, level, phase, message):
        """
        Imaging client logs sent to mmc.

        This logs will be displayed on imaging log tab of computer.

        @param mac: MAC address
        @type mac: str

        @param level: logging message level
        @type level: int

        @param phase: step of imaging workflow
        @type phase: str

        @param message: displayed message
        @type message: str

        @return: ACK to confirm a correct reception, otherwise ERROR
        @rtype: str
        """

        level += 1 # (different offset on imaging client)

        if level == 3:
            self.lasttime = 0
            self.lastfile = 0

        d = self.api.logClientAction(mac, level, phase, repr(message))

        @d.addCallback
        def _cb(result):
            EntryTracking().search_and_extract(mac, phase, message)
            if level != 1 :
                return "ACK"

        @d.addErrback
        def _eb(failure):
            logging.getLogger().warn("PXE Proxy: logging action failed: %s" % str(failure))
            return "ERROR"

        return d


    @assign(0x1B)
    def getComputerUUID(self, mac):
        """
        Returns computer's UUID if exists. Used for backup processing.

        @param mac: MAC address
        @type mac: str

        @return: computer's UUID if exists, otherwise None
        @rtype: str
        """
        self.api.logClientAction(mac,
                                 LOG_LEVEL.DEBUG,
                                 LOG_STATE.BOOT,
                                 "computer UUID request")
        d = self.api.getComputerByMac(mac)

        @d.addCallback
        def cb(result):
            if isinstance(result, dict):
                if "uuid" in result :
                    uuid = result["uuid"]
                    self.api.logClientAction(mac,
                                             LOG_LEVEL.DEBUG,
                                             LOG_STATE.BOOT,
                                             "computer uuid sent: %s" % uuid)
                    return uuid

        @d.addErrback
        def _eb(failure):
            self.api.logClientAction(mac,
                                     LOG_LEVEL.ERR,
                                     LOG_STATE.BOOT,
                                     "failed to recover a computer UUID")
            logging.getLogger().warn("PXE Proxy: computer's UUID get failed: %s" % str(failure))

        return d

    @assign(0x1A)
    def getComputerHostname(self, mac):
        """
        Returns computer'hostname if exists.
        Used for menu title and backup processing.

        @param mac: MAC address
        @type mac: str

        @return: computer's hostname if exists, otherwise None
        @rtype: str
        """
        self.api.logClientAction(mac,
                                 LOG_LEVEL.DEBUG,
                                 LOG_STATE.BOOT,
                                 "hostname request")
        d = self.api.getComputerByMac(mac)

        @d.addCallback
        def _cb(result):
            if isinstance(result, dict):
                if "shortname" in result :
                    hostname = result["shortname"]
                    self.api.logClientAction(mac,
                                             LOG_LEVEL.DEBUG,
                                             LOG_STATE.BOOT,
                                             "hostname sent: %s" % hostname)
                    return hostname

        @d.addErrback
        def _eb(failure):
           self.api.logClientAction(mac,
                                    LOG_LEVEL.ERR,
                                    LOG_STATE.BOOT,
                                    "failed to obtain a hostname")
           logging.getLogger().warn("PXE Proxy: computer's hostname get failed: %s" % str(failure))


        return d

    @assign(0x54)
    def imagingServerStatus(self, mac, pnum, bnum, to):
        """
        Returns the percentage of remaining size from the part where the images are stored.

        @param mac: MAC address
        @type mac: str

        @return: a percentage, or -1 if it fails
        @rtype: int
        """
        d = Deferred()

        @d.addCallback
        @d.addErrback
        def _eb(failure):
            logging.getLogger().warn("PXE Proxy: server status get failed: %s" % str(failure))

        d.callback(True)
        return d
