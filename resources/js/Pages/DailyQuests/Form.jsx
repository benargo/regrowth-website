import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Autocomplete from "@/Components/Autocomplete";
import { Form } from "@inertiajs/react";
import { useState } from "react";

export default function DailyQuestsForm({
    cookingQuests,
    fishingQuests,
    dungeonQuests,
    heroicQuests,
    pvpQuests,
    icons,
}) {
    const [cookingInput, setCookingInput] = useState("");
    const [fishingInput, setFishingInput] = useState("");
    const [dungeonInput, setDungeonInput] = useState("");
    const [heroicInput, setHeroicInput] = useState("");
    const [pvpInput, setPvpInput] = useState("");
    const [cookingQuestId, setCookingQuestId] = useState("");
    const [fishingQuestId, setFishingQuestId] = useState("");
    const [dungeonQuestId, setDungeonQuestId] = useState("");
    const [heroicQuestId, setHeroicQuestId] = useState("");
    const [pvpQuestId, setPvpQuestId] = useState("");

    // Normalize string for comparison: remove accents, normalize apostrophes, lowercase
    const normalizeString = (str) => {
        if (!str) return "";
        return str
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "") // Remove diacritics
            .replace(/[\u2018\u2019]/g, "'") // Normalize curly apostrophes to straight
            .toLowerCase()
            .trim();
    };

    // Find quest by partial matching on name or instance
    const findMatchingQuest = (quests, searchValue) => {
        const normalized = normalizeString(searchValue);
        return quests.find((q) => {
            const questName = normalizeString(q.name);
            const instanceName = normalizeString(q.instance);
            return (
                questName === normalized ||
                instanceName === normalized ||
                questName.includes(normalized) ||
                instanceName.includes(normalized)
            );
        });
    };

    const handleCookingChange = (value) => {
        setCookingInput(value);
        const normalized = normalizeString(value);
        const matchingQuest = cookingQuests.find((q) => {
            const questName = normalizeString(q.name);
            return questName === normalized || questName.includes(normalized);
        });
        setCookingQuestId(matchingQuest ? matchingQuest.id : "");
    };

    const handleFishingChange = (value) => {
        setFishingInput(value);
        const normalized = normalizeString(value);
        const matchingQuest = fishingQuests.find((q) => {
            const questName = normalizeString(q.name);
            return questName === normalized || questName.includes(normalized);
        });
        setFishingQuestId(matchingQuest ? matchingQuest.id : "");
    };

    const handleDungeonChange = (value) => {
        setDungeonInput(value);
        const matchingQuest = findMatchingQuest(dungeonQuests, value);
        setDungeonQuestId(matchingQuest ? matchingQuest.id : "");
    };

    const handleHeroicChange = (value) => {
        setHeroicInput(value);
        const matchingQuest = findMatchingQuest(heroicQuests, value);
        setHeroicQuestId(matchingQuest ? matchingQuest.id : "");
    };

    const handlePvpChange = (value) => {
        setPvpInput(value);
        const normalized = normalizeString(value);
        const matchingQuest = pvpQuests.find((q) => {
            const questName = normalizeString(q.name);
            return questName === normalized || questName.includes(normalized);
        });
        setPvpQuestId(matchingQuest ? matchingQuest.id : "");
    };
    return (
        <Master title="Set Daily Quests">
            {/* Header */}
            <SharedHeader backgroundClass="bg-dungeons" title="Set Daily Quests" />

            {/* Content */}
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    <p className="mb-6 text-lg text-gray-300">
                        Use the form below to select the current daily quests for professions, dungeons, and PvP. Once you submit, the selected quests will be posted to the configured Discord channel.
                    </p>
                    <Form action={route("dashboard.daily-quests.store")} method="post">
                        {({ errors, processing, wasSuccessful }) => (
                            <>
                                <h2 className="text-2xl font-semibold mb-2">Daily profession quests</h2>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 mb-8">
                                    {/* Cooking Quest Selection */}
                                    <div>
                                        <Autocomplete
                                            value={cookingInput}
                                            onChange={handleCookingChange}
                                            options={cookingQuests}
                                            placeholder="Type quest name..."
                                            icon={icons.cooking}
                                            iconAlt="Cooking Icon"
                                            labelText="Cooking"
                                            error={errors.cooking_quest_id}
                                        />
                                        <input
                                            type="hidden"
                                            name="cooking_quest_id"
                                            value={cookingQuestId}
                                        />
                                    </div>
                                    {/* Fishing Quest Selection */}
                                    <div>
                                        <Autocomplete
                                            value={fishingInput}
                                            onChange={handleFishingChange}
                                            options={fishingQuests}
                                            placeholder="Type quest name..."
                                            icon={icons.fishing}
                                            iconAlt="Fishing Icon"
                                            labelText="Fishing"
                                            error={errors.fishing_quest_id}
                                        />
                                        <input
                                            type="hidden"
                                            name="fishing_quest_id"
                                            value={fishingQuestId}
                                        />
                                    </div>
                                </div>

                                <h2 className="mb-2 block text-2xl font-semibold">Daily dungeon quests</h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 mb-8 gap-4">
                                    {/* Normal difficulty */}
                                    <div>
                                        <Autocomplete
                                            value={dungeonInput}
                                            onChange={handleDungeonChange}
                                            options={dungeonQuests}
                                            placeholder="Type dungeon or quest name..."
                                            icon={icons.dungeon}
                                            iconAlt="Dungeon Icon"
                                            labelText="Normal"
                                            error={errors.dungeon_quest_id}
                                            renderOption={(quest) => (
                                                <div>
                                                    <div className="font-medium">{quest.name}</div>
                                                    {quest.instance && (
                                                        <div className="text-sm text-gray-400">
                                                            {quest.instance}
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        />
                                        <input
                                            type="hidden"
                                            name="dungeon_quest_id"
                                            value={dungeonQuestId}
                                        />
                                    </div>
                                    {/* Heroic difficulty */}
                                    <div>
                                        <Autocomplete
                                            value={heroicInput}
                                            onChange={handleHeroicChange}
                                            options={heroicQuests}
                                            placeholder="Type dungeon or quest name..."
                                            icon={icons.heroic}
                                            iconAlt="Heroic Icon"
                                            labelText="Heroic"
                                            error={errors.heroic_quest_id}
                                            renderOption={(quest) => (
                                                <div>
                                                    <div className="font-medium">{quest.name}</div>
                                                    {quest.instance && (
                                                        <div className="text-sm text-gray-400">
                                                            {quest.instance}
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        />
                                        <input
                                            type="hidden"
                                            name="heroic_quest_id"
                                            value={heroicQuestId}
                                        />
                                    </div>
                                </div>

                                <h2 className="mb-2 block text-2xl font-semibold">Daily PvP quests</h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 mb-8 gap-4">
                                    {/* PvP Quest Selection */}
                                    <div className="mt-8 md:mt-0">
                                        <Autocomplete
                                            value={pvpInput}
                                            onChange={handlePvpChange}
                                            options={pvpQuests}
                                            placeholder="Type quest name..."
                                            icon={icons.pvp}
                                            iconAlt="PvP Icon"
                                            labelText="Battleground"
                                            error={errors.pvp_quest_id}
                                        />
                                        <input
                                            type="hidden"
                                            name="pvp_quest_id"
                                            value={pvpQuestId}
                                        />
                                    </div>
                                </div>

                                {/* Submit Button */}
                                <div className="flex justify-end gap-4 pt-4">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded bg-amber-600 px-6 py-3 font-semibold text-white transition hover:bg-amber-700 disabled:opacity-50"
                                    >
                                        {processing ? "Posting..." : "Post to Discord"}
                                    </button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </Master>
    );
}
