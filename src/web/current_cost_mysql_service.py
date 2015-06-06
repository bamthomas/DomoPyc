# coding=utf-8
import asyncio
from datetime import datetime, timedelta


class CurrentCostDatabaseReader(object):
    def __init__(self, pool):
        self.pool = pool

    @asyncio.coroutine
    def get_history(self):
        with (yield from self.pool) as conn:
            cur = yield from conn.cursor()
            yield from cur.execute("SELECT timestamp(date(timestamp), MAKETIME(0,0,0)) AS day, "
                                   "round(sum(watt * minutes)) / (60 * 1000) FROM current_cost "
                                   "GROUP BY date(timestamp) ORDER BY day ")
            result = yield from cur.fetchall()
            yield from cur.close()
            return result

    @asyncio.coroutine
    def get_costs(self, since):
        with (yield from self.pool) as conn:
            cur = yield from conn.cursor()
            yield from cur.execute("SELECT timestamp(date(timestamp), MAKETIME(0,0,0)) AS day, "
                                   "sum(watt * minutes) / (60 * 1000) FROM current_cost "
                                   "where UNIX_TIMESTAMP(timestamp) > %s "
                                   "GROUP BY date(timestamp) ORDER BY day " % since)
            result = yield from cur.fetchall()
            yield from cur.close()
            return result

    @asyncio.coroutine
    def get_by_day(self, date_time):
        with (yield from self.pool) as conn:
            cur = yield from conn.cursor()
            yield from cur.execute("SELECT timestamp, watt, temperature from current_cost where timestamp >= %s "
                                   "and timestamp < %s ORDER BY TIMESTAMP ", (date_time, date_time + timedelta(days=1)))
            result = yield from cur.fetchall()
            yield from cur.close()
            return result
