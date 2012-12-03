# coding=utf-8
from datetime import datetime, timedelta
from json import loads, dumps
from json.decoder import JSONDecoder
import logging
import threading
from xml.etree.ElementTree import XML, XMLParser, ParseError
import iso8601
import serial
import redis

CURRENT_COST = 'current_cost'
REDIS = redis.Redis()
__author__ = 'bruno'

logging.basicConfig(format='%(asctime)s [%(name)s] %(levelname)s: %(message)s')
LOGGER = logging.getLogger('current_cost')

class CurrentCostReader(threading.Thread):
    def __init__(self, serial_drv, publish_func):
        super(CurrentCostReader, self).__init__(target=self.read_sensor)
        self.serial_drv = serial_drv
        self.publish = publish_func
        self.stop_asked = threading.Event()

    def read_sensor(self):
        try:
            while not self.stop_asked.is_set():
                line = self.serial_drv.readline()
                if line:
                    try:
                        xml_data = XML(line, XMLParser())
                        if len(xml_data) >= 7 and xml_data[2].tag == 'time' and xml_data[7].tag == 'ch1':
                            power = int(xml_data[7][0].text)
                            self.publish({'date':now().isoformat(), 'watt':power, 'temperature':float(xml_data[3].text)})
                    except ParseError as xml_parse_error:
                        LOGGER.exception(xml_parse_error)
        finally:
            self.serial_drv.close()

    def stop(self):
        self.stop_asked.set()

def now(): return datetime.now()

def redis_publish(event_dict):
    REDIS.publish(CURRENT_COST, dumps(event_dict))

class RedisSubscriber(threading.Thread):
    def __init__(self, redis, message_handler):
        super(RedisSubscriber, self).__init__(target=self.wait_messages)
        self.message_handler = message_handler
        self.pubsub = redis.pubsub()
        self.pubsub.subscribe(CURRENT_COST)

    def wait_messages(self):
        for item in self.pubsub.listen():
            if item['type'] == 'message':
                self.message_handler.handle(item['data'])

    def stop(self):
        self.pubsub.unsubscribe(CURRENT_COST)

class AverageMessageHandler(object):
    def __init__(self, average_period_minutes=0):
        self.delta_minutes = timedelta(minutes=average_period_minutes)
        self.next_save_date = now() + self.delta_minutes
        self.messages = []

    def push_redis(self, key, json_message):
        if REDIS.lpush(key, json_message) == 1:
            REDIS.expire(key, 5 * 24 * 3600)

    def handle(self, json_message):
        message = loads(json_message)
        message_date = iso8601.parse_date(message['date'])
        key = 'current_cost_' + message_date.strftime('%Y-%m-%d')
        self.messages.append(message)
        if now() > self.next_save_date:
            self.push_redis(key, self.get_average_json_message(message['date']))
            self.next_save_date = self.next_save_date + self.delta_minutes
            self.messages = []

    def get_average_json_message(self, date):
        watt_and_temp = map(lambda msg: (msg['watt'],msg['temperature']), self.messages)
        watt_sum, temp_sum = reduce(lambda (x,t),(y,v): (x+y, t+v), watt_and_temp)
        nb_messages = len(self.messages)
        return dumps({'date': date, 'watt': watt_sum/ nb_messages, 'temperature': temp_sum / nb_messages})

if __name__ == '__main__':
    serial_drv = serial.Serial('/dev/ttyUSB0', baudrate=57600,
            bytesize=serial.EIGHTBITS, parity=serial.PARITY_NONE, stopbits=serial.STOPBITS_ONE, timeout=10)
    current_cost = CurrentCostReader(serial_drv, redis_publish)
    current_cost.start()

    redis_save_consumer = RedisSubscriber(REDIS, AverageMessageHandler(10))
    redis_save_consumer.start()

    current_cost.join()

