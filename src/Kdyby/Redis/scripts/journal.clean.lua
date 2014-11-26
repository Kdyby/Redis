
local conds = cjson.decode(ARGV[1])

if conds["all"] ~= nil then
    batchDelete(redis.call('keys', "Nette.Journal:*"))
    if conds["delete-entries"] ~= nil then
        batchDelete(redis.call('keys', "Nette.Storage:*"))
    end

    return redis.status_reply("Ok")
end

local entries = {}

local processFoundKeys = function (found)
    if #found > 0 then
        for i, key in pairs(found) do
            if conds["delete-entries"] ~= nil then
                redis.call("del", key)
            else
                entries[#entries + 1] = key
            end
        end
    end
end

if conds["tags"] ~= nil then
    local formattedTagKeys = {}
    for i, tag in pairs(conds["tags"]) do
        processFoundKeys(tagEntries(tag))
        formattedTagKeys[#formattedTagKeys + 1] = formatKey(tag, 'keys')
    end
    if #formattedTagKeys > 0 then
        redis.call("del", unpack(formattedTagKeys))
    end
end

if conds["priority"] ~= nil then
    processFoundKeys(priorityEntries(conds["priority"]))
    redis.call('zRemRangeByScore', formatKey('priority'), 0, conds["priority"])
end

return entries
