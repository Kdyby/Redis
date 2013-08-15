
local dp = cjson.decode(ARGV[1])

-- clean the entry key
cleanEntry({KEYS[1]})

-- write the entry key
-- redis.call('multi')

-- add entry to each tag & tag to entry
if dp["tags"] ~= nil then
    for i, tag in pairs(dp["tags"]) do
        redis.call('sAdd', formatKey(tag, "keys") , KEYS[1])
        redis.call('sAdd', formatKey(KEYS[1], "tags") , tag)
    end
end

if dp["priority"] ~= nil then
    redis.call('zAdd', formatKey("priority"), dp["priority"], KEYS[1])
end

-- redis.call('exec')

return redis.status_reply("Ok")
