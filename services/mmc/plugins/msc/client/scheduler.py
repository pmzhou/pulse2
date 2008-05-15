# -*- coding: utf-8; -*-
#
# (c) 2004-2007 Linbox / Free&ALter Soft, http://linbox.com
# (c) 2007 Mandriva, http://www.mandriva.com/
#
# $Id$
#
# This file is part of Mandriva Management Console (MMC).
#
# MMC is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# MMC is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MMC; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

# Twisted
import twisted.web.xmlrpc
import logging

# our stuff
from mmc.plugins.msc.config import MscConfig

def start_all_commands(scheduler):
    mydeffered = twisted.web.xmlrpc.Proxy(select_scheduler(scheduler)).callRemote(
        'start_all_commands'
    )
    return True

def ping_client(scheduler, computer):
    """
    expected struct for computer:
        [
            None,
            {
                'macAddress': ['00:16:E6:0D:E8:DF'],
                'displayName': ['NomType=IBM-E50-8818-D34\r\nCl\xe9 N\xb0338\r\nsalle de formation 5\xe8me '],
                'cn': ['SFORM03W'],
                'objectUUID': ['UUID8096'],
                'ipHostNumber': ['55.100.6.81'],
                'fullname': 'SFORM03W.D11510100.cpam-reims.cnamts.fr'
            }
        ]
    """
    def parseResult(result):
        logging.getLogger().debug('Ping computer %s: %s' % (computer, result))
        return result
    def parseError(reason):
        # FIXME: handle error
        return False
    # ping is done using available data:
    # - uuid
    # - fullname
    # - cn[]
    # - ipHostNumber[]
    # - macAddress[]
    mydeffered = twisted.web.xmlrpc.Proxy(select_scheduler(scheduler)).callRemote(
        'ping_client',
        computer[1]['objectUUID'][0],
        computer[1]['fullname'],
        computer[1]['cn'][0],
        computer[1]['ipHostNumber'],
        computer[1]['macAddress'],
    )
    mydeffered.addCallback(parseResult).addErrback(parseResult)
    return mydeffered

def probe_client(scheduler, computer):
    def parseResult(result):
        logging.getLogger().debug('Probe client %s: %s' % (computer, result))
        return result
    def parseError(reason):
        # FIXME: handle error
        return False
    # probe is done using available data:
    # - uuid
    # - fullname
    # - cn[]
    # - ipHostNumber[]
    # - macAddress[]
    mydeffered = twisted.web.xmlrpc.Proxy(select_scheduler(scheduler)).callRemote(
        'probe_client',
        computer[1]['objectUUID'][0],
        computer[1]['fullname'],
        computer[1]['cn'][0],
        computer[1]['ipHostNumber'],
        computer[1]['macAddress'],
    )
    mydeffered.addCallback(parseResult).addErrback(parseResult)
    return mydeffered

def select_scheduler(scheduler_name):
    schedulers = MscConfig('msc').schedulers
    if not scheduler_name:
        scheduler = schedulers[schedulers.keys()[0]]
    else:
        scheduler = schedulers[scheduler_name]

    uri = 'http://'
    if scheduler['username'] != '':
        uri += '%s:%s@' % (scheduler['username'], scheduler['password'])
    uri += '%s:%d' % (scheduler['host'], int(scheduler['port']))

    return uri
