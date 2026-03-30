import pymysql

conn = pymysql.connect(
    host='56b4c23f6853c.gz.cdb.myqcloud.com',
    port=14413,
    user='mbti',
    password='Zhiqun1984',
    database='mbti',
    charset='utf8mb4'
)
cur = conn.cursor()

cur.execute("SHOW COLUMNS FROM mbti_enterprises LIKE 'permissions'")
row = cur.fetchone()
if row:
    print('Column permissions already exists, skipping.')
else:
    cur.execute("""
        ALTER TABLE mbti_enterprises
        ADD COLUMN permissions json NULL
        COMMENT '功能权限开关 {face,mbti,pdp,disc,distribution}'
        AFTER status
    """)
    conn.commit()
    print('Column permissions added successfully.')

conn.close()
