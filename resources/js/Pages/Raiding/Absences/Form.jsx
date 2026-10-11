import { useMemo } from "react";
import { useForm, usePage } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Icon from "@/Components/FontAwesome/Icon";
import EmptyState from "@/Components/EmptyState";
import UserSearch from "@/Components/Discord/UserSearch";
import GameVersionSelector from "@/Components/GameVersions/GameVersionSelector";
import CharacterSearch from "@/Components/Characters/CharacterSearch";
import { FormRow, FormSection } from "@/Components/FormControls";
import AbsenceForm from "@/Components/PlannedAbsences/AbsenceForm";
import AbsenceDetailsFields from "@/Components/PlannedAbsences/AbsenceDetailsFields";
import AbsenceFormLayout from "@/Components/PlannedAbsences/AbsenceFormLayout";
import normaliseCharacterName from "@/Helpers/NormaliseCharacterName";

/**
 * Find the one main whose name matches the Discord nickname, either its first
 * word or the whole of it. Returns null when none or several match.
 */
function matchNickname(characters, nickname) {
    const candidates = new Set([nickname.trim().split(/\s+/)[0], nickname.trim()].map(normaliseCharacterName));
    const matches = characters.filter((character) => candidates.has(normaliseCharacterName(character.name)));

    return matches.length === 1 ? matches[0] : null;
}

function OfficerAbsenceForm({ gameVersion, characters, plannedAbsence, action }) {
    const isEditing = plannedAbsence !== null;
    const originalCharacterId = plannedAbsence?.character?.id ?? null;

    const { data, setData, post, patch, transform, processing, errors, clearErrors } = useForm({
        game_version: gameVersion?.id ?? null,
        character: originalCharacterId,
        user: plannedAbsence?.user?.id ?? null,
        start_date: plannedAbsence?.start_date ?? "",
        end_date: plannedAbsence?.end_date ?? "",
        reason: plannedAbsence?.reason ?? "",
    });

    // A legacy absence may belong to a character that is no longer a main; keep it selectable as the current value.
    const selectableCharacters = useMemo(() => {
        const current = plannedAbsence?.character;

        if (!current || characters.some((character) => character.id === current.id)) {
            return characters;
        }

        return [current, ...characters];
    }, [characters, plannedAbsence]);

    function handleUserSelect(member) {
        setData("user", member?.id ?? null);

        if (!member?.nickname || data.character) {
            return;
        }

        const matched = matchNickname(characters, member.nickname);

        if (matched) {
            setData((previous) => ({ ...previous, user: member.id, character: matched.id }));
        }
    }

    function submit(visitOptions) {
        transform(({ game_version, character, user, end_date, ...rest }) => ({
            ...rest,
            end_date: end_date || null,
            ...(user ? { user } : {}),
            ...(!isEditing || character !== originalCharacterId ? { game_version, character } : {}),
        }));

        if (isEditing) {
            patch(action, visitOptions);
        } else {
            post(action, visitOptions);
        }
    }

    const characterError = errors.character ?? errors.game_version;

    return (
        <AbsenceForm
            errors={errors}
            processing={processing}
            onSubmit={submit}
            submitLabel={isEditing ? "Save changes" : "Log absence"}
            cancelHref={route("raiding.absences.index")}
        >
            <FormSection id="whos-away" legend="Who's away">
                <FormRow
                    htmlFor="user"
                    label="Discord user"
                    hint="Picking someone also picks their main, if their nickname matches one."
                    error={errors.user}
                >
                    <UserSearch
                        id="user"
                        value={data.user}
                        onSelect={handleUserSelect}
                        invalid={Boolean(errors.user)}
                    />
                </FormRow>

                <FormRow htmlFor="character" label="Character" required error={characterError}>
                    <CharacterSearch
                        id="character"
                        characters={selectableCharacters}
                        value={data.character}
                        onChange={(id) => {
                            setData("character", id);
                            if (id) {
                                clearErrors("character", "game_version");
                            }
                        }}
                        invalid={Boolean(characterError)}
                        emptyMessage={`No mains on the ${gameVersion.title} roster match your search.`}
                    />
                </FormRow>
            </FormSection>

            <AbsenceDetailsFields data={data} setData={setData} errors={errors} clearErrors={clearErrors} canBackdate isOfficer />
        </AbsenceForm>
    );
}

export default function Form() {
    const {
        game_versions: gameVersions = null,
        game_version: gameVersion = null,
        characters,
        planned_absence: plannedAbsence = null,
        action,
    } = usePage().props;

    const pageTitle = plannedAbsence ? "Edit Planned Absence" : "Log Planned Absence";

    return (
        <Master title={pageTitle}>
            <SharedHeader title={pageTitle} backgroundClass="bg-illidan" />

            <ToolNav>
                <ToolNavLink href={route("raiding.absences.index")}>
                    <Icon icon="arrow-left" style="solid" className="mr-2" />
                    <span>Back to planned absences</span>
                </ToolNavLink>
            </ToolNav>

            <PageContainer>
                {gameVersions && (
                    <div className="mb-10">
                        <GameVersionSelector gameVersions={gameVersions} currentGameVersion={gameVersion} />
                    </div>
                )}

                {gameVersion ? (
                    <AbsenceFormLayout>
                        <OfficerAbsenceForm
                            key={gameVersion.slug}
                            gameVersion={gameVersion}
                            characters={characters}
                            plannedAbsence={plannedAbsence}
                            action={action}
                        />
                    </AbsenceFormLayout>
                ) : (
                    <EmptyState
                        icon="calendar-times"
                        message="There's no released game version to log an absence against."
                    />
                )}
            </PageContainer>
        </Master>
    );
}
